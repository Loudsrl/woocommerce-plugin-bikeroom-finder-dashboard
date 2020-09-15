<?php


class ProductsFinderController extends WP_REST_Controller
{

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'api/v' . $version;
        $base = 'products';
        register_rest_route($namespace, '/' . $base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => array(
                    'brand_id' => array(
                        'sanitize_callback' => 'absint',
                    ),
                    'q' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args' => array(
                    'context' => array(
                        'default' => 'view',
                    ),
                ),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_public_item_schema'),
        ));
    }

    /**
     * Get a collection of items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request)
    {
        $args = [
            "status" => "publish",
            "type" => ["grouped"],
        ];
        if ($brand_id = $request->get_param('brand_id')) {
            $args["brand_ids"] = [$brand_id];
        }
        if ($query = $request->get_param('q')) {
            $args["search"] = $query;
        }
        $items = wc_get_products($args);
        $data = array();
        foreach ($items as $item) {
            $itemdata = $this->prepare_item_for_response($item, $request);
            $data[] = $this->prepare_response_for_collection($itemdata);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Get one item from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_item($request)
    {
        $params = $request->get_params();
        $id = $params['id'];
        $item = wc_get_product($id);
        $data = $this->prepare_item_for_response($item, $request);
        if (!empty($data)) {
            return new WP_REST_Response($data, 200);
        } else {
            return new WP_Error(404, __('Cannot find product with given ID'));
        }
    }

    /**
     * Check if a given request has access to get items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check($request)
    {
        return current_user_can('edit_products');
    }

    /**
     * Check if a given request has access to get a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
    }


    /**
     * Prepare the item for the REST response
     *
     * @param mixed $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     * @return mixed
     */
    public function prepare_item_for_response($item, $request)
    {
        if (!empty($item) && $item instanceof WC_Product) {
            $data = $item->get_data();
            $image = null;
            if (!empty($data['image_id']) && ($img_id = intval($data['image_id'])) && ($img = wp_get_attachment_url($img_id))) {
                $image = $img;
            }
            $data['image'] = $image;
            $gallery = array();
            if (!empty($data['gallery_image_ids']) && is_array($data['gallery_image_ids']) && ($imgs = $data['gallery_image_ids'])) {
                foreach ($imgs as $img) {
                    if (!empty($img) && ($img_id = intval($img)) && ($url = wp_get_attachment_url($img_id))) {
                        array_push($gallery, $url);
                    }
                }
            }
            $data['gallery'] = $gallery;
            $attributes = $item->get_attributes();
            $atts = array();
            foreach ($attributes as $key => $value) {
                $atts[$key] = $value->get_data();
                $atts[$key]['options'] = $value->get_terms();
            }
            $data['attributes'] = $atts;

            return $data;
        }
        return null;
    }

    /**
     * Get the query params for collections
     *
     * @return array
     */
    public function get_collection_params()
    {
        return array(
            'page' => array(
                'description' => 'Current page of the collection.',
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description' => 'Maximum number of items to be returned in result set.',
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint',
            ),
            'search' => array(
                'description' => 'Limit results to those matching a string.',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }
}
