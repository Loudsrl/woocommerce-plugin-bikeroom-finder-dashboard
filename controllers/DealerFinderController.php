<?php


class DealerFinderController extends WP_REST_Controller
{

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'api/v' . $version;
        $base = 'dealer';
        register_rest_route($namespace, '/' . $base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_dealer'),
                'permission_callback' => array($this, 'get_dealer_permissions_check'),
                'args' => array(),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/products', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => array(),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/products/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args' => array(),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/products/', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args' => array(
                    'parent_id' => array(
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ),
                    'size' => array(
                        'required' => true,
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                    'color' => array(
                        'required' => true,
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                    'price' => array(
                        'required' => true,
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                ),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/products/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'edit_item'),
                'permission_callback' => array($this, 'edit_item_permissions_check'),
                'args' => array(
                    'size' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                    'color' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                    'price' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                ),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/products/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
                'args' => array(),
            ),
        ));
        register_rest_route($namespace, '/' . $base . '/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_public_item_schema'),
        ));
    }

    /**
     * Get the dealer
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_dealer($request)
    {
        $current_user = wp_get_current_user();
        $user = $current_user->to_array();
        $metas = get_user_meta($current_user->ID);
        $meta = array();
        foreach ($metas as $key => $value) {
            if (!empty($value) && is_array($value) && count($value) == 1) {
                $meta[$key] = $value[0];
            } else if (!empty($value)) {
                $meta[$key] = $value;
            }
        }
        $user['meta'] = $meta;
        return new WP_REST_Response($user, 200);
    }

    /**
     * Get a collection of items
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request)
    {
        $current_user = wp_get_current_user();
        $args = [
            "status" => "publish",
            "type" => ["simple"],
            "owner_id" => $current_user->ID,
        ];
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
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_item($request)
    {
        $id = $request->get_param('id');
        $item = wc_get_product($id);
        $data = $this->prepare_item_for_response($item, $request);
        if (!empty($data)) {
            return new WP_REST_Response($data, 200);
        } else {
            return new WP_Error(404, __('Cannot find product with given ID'));
        }
    }

    /**
     * Create a new item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|WP_REST_Response
     * @throws WC_Data_Exception
     */
    public function create_item($request)
    {
        $user = wp_get_current_user();
        $params = $request->get_params();
        $parent_id = $params["parent_id"] ?? null;
        if (empty($parent_id)) {
            return new WP_Error(400, __('Cannot create product with given information'));
        }
        $parent_product = wc_get_product($parent_id);
        $color = $params["color"] ?? null;
        $size = $params["size"] ?? null;
        $price = array_has($params, "price") ? floatval($params["price"]) : null;
        $region = get_user_meta($user->ID, "region", true);
        $city = get_user_meta($user->ID, "city", true);
        if (empty($parent_product) || empty($color) || empty($size) || empty($price) || !is_float($price) || !($parent_product instanceof WC_Product_Grouped)) {
            return new WP_Error(400, __('Cannot create product with given information'));
        }

        $sku = $parent_product->get_sku() . "-" . sanitize_title($user->user_login) . "-" . sanitize_title($color) . "-" . sanitize_title($size);
        $title = $parent_product->get_title() . " - " . $user->user_login;
        $data = $parent_product->get_data();
        $data['id'] = null;
        $data['date_created'] = new WC_DateTime();
        $data['date_modified'] = new WC_DateTime();
        $brand_term = wp_get_post_terms($parent_id, "brand");
        $condition_term = get_term_by('slug', 'new', 'condition');

        $attrs = array_map(function ($a) use ($color, $size) {
            if ($a instanceof WC_Product_Attribute) {
                if ($a->get_name() === "pa_color") {
                    $a->set_options(array($color));
                }
                if ($a->get_name() === "pa_size") {
                    $a->set_options(array($size));
                }
            }
            return $a;
        }, $data['attributes']);

        $region_taxonomy = wc_attribute_taxonomy_id_by_name('pa_region');
        $city_taxonomy = wc_attribute_taxonomy_id_by_name('pa_city');

        if (!$region_taxonomy) {
            $region_taxonomy = wc_create_attribute(array(
                "name" => "region",
                "slug" => "region",
                "type" => "select",
                "order_by" => "name",
                "has_archives" => false
            ));
        }
        if (!$city_taxonomy) {
            $city_taxonomy = wc_create_attribute(array(
                "name" => "city",
                "slug" => "city",
                "type" => "select",
                "order_by" => "name",
                "has_archives" => false
            ));
        }

        $region_attr = new WC_Product_Attribute();
        $region_attr->set_id($region_taxonomy);
        $region_attr->set_name('pa_region');
        $region_attr->set_options(array($region));
        $region_attr->set_position(1);
        $region_attr->set_visible(1);

        $city_attr = new WC_Product_Attribute();
        $city_attr->set_id($city_taxonomy);
        $city_attr->set_name('pa_city');
        $city_attr->set_options(array($city));
        $city_attr->set_visible('1');

        array_push($attrs, $region_attr);
        array_push($attrs, $city_attr);

        $data['attributes'] = $attrs;

        $product = new WC_Product_Simple();
        $product->set_props($data);
        $product->set_sku($sku);
        $product->set_name($title);
        $product->set_regular_price($price);
        $pid = $product->save();
        wp_set_post_terms($pid, $brand_term[0]->term_id, 'brand');
        wp_set_post_terms($pid, $condition_term->term_id, 'condition');

        $children_products = $parent_product->get_children();
        array_push($children_products, $pid);
        $parent_product->set_children($children_products);
        $parent_product->save();

        $item = $product;
        $data = $this->prepare_item_for_response($item, $request);

        if (!empty($data)) {
            return new WP_REST_Response($data, 200);
        } else {
            return new WP_Error(404, __('Cannot find product with given ID'));
        }

    }

    /**
     * Edit a specified item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function edit_item($request)
    {
        $user = wp_get_current_user();
        $params = $request->get_params();
        $id = $params["id"] ?? null;
        if (empty($id)) {
            return new WP_Error(404, __('Cannot find product with given ID associated with the current dealer.'));
        }
        $product = wc_get_product($id);
        $author = intval(get_post_field("post_author", $id, 'raw') ?? 0);
        $color = $params["color"] ?? null;
        $size = $params["size"] ?? null;
        $price = array_has($params, "price") ? floatval($params["price"]) : null;

        if (empty($product) || !($product instanceof WC_Product_Simple) || $author === 0 || $author !== $user->ID) {
            return new WP_Error(404, __('Cannot find product with given ID associated with the current dealer.'));
        }

        $attrs = array_map(function ($a) use ($color, $size) {
            if ($a instanceof WC_Product_Attribute) {
                if (!empty($color) && $a->get_name() === "pa_color") {
                    $a->set_options(array($color));
                }
                if (!empty($size) && $a->get_name() === "pa_size") {
                    $a->set_options(array($size));
                }
            }
            return $a;
        }, $product->get_attributes());

        $product->set_attributes($attrs);

        if (!empty($price) && is_float($price)) {
            $product->set_regular_price($price);
        }

        $product->save();
        $item = $product;
        $data = $this->prepare_item_for_response($item, $request);
        if (!empty($data)) {
            return new WP_REST_Response($data, 200);
        } else {
            return new WP_Error(404, __('Cannot find product with given ID'));
        }
    }


    /**
     * delete an items
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function delete_item($request)
    {
        $user = wp_get_current_user();
        $id = $request->get_param("id");
        $product = wc_get_product($id);
        $author = intval(get_post_field("post_author", $id, 'raw'));
        if (!empty($product) && $author === $user->ID) {
            $product->delete(true);
            return new WP_REST_Response(["status" => "OK"], 200);
        }
        return new WP_Error(404, __('Cannot delete product with given ID'));
    }

    /**
     * Check if a given request has access to get items
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */
    public
    function get_items_permissions_check(
        $request
    )
    {
        return current_user_can('edit_products');
    }

    /**
     * Check if a given request has access to get a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */
    public
    function get_item_permissions_check(
        $request
    )
    {
        return $this->get_items_permissions_check($request);
    }

    /**
     * Check if a given request has access to get a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */
    public
    function get_dealer_permissions_check(
        $request
    )
    {
        return $this->get_items_permissions_check($request);
    }

    /**
     * Check if a given request has access to edit a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */
    public
    function edit_item_permissions_check(
        $request
    )
    {
        return $this->get_items_permissions_check($request);
    }

    /**
     * Check if a given request has access to create a new item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */
    public
    function create_item_permissions_check(
        $request
    )
    {
        return $this->get_items_permissions_check($request);
    }


    /**
     * Check if a given request has access to delete an item
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */
    public
    function delete_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
    }


    /**
     * Prepare the item for the REST response
     *
     * @param mixed $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     *
     * @return mixed
     */
    public
    function prepare_item_for_response(
        $item, $request
    )
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
    public
    function get_collection_params()
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
