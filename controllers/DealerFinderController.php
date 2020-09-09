<?php


class DealerFinderController extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'api/v' . $version;
		$base      = 'dealer';
		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dealer' ),
				'permission_callback' => array( $this, 'get_dealer_permissions_check' ),
				'args'                => array(),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/products', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/products/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/products/', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'parent_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'size' => array(
						'required' => true,
						'validate_callback' => 'is_string',
					),
					'color' => array(
						'required' => true,
						'validate_callback' => 'is_string',
					),
					'price' => array(
						'required' => true,
						'validate_callback' => 'is_string',
					),
				),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/products/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'edit_item' ),
				'permission_callback' => array( $this, 'edit_item_permissions_check' ),
				'args'                => array(
					'size' => array(
						'validate_callback' => 'is_string',
					),
					'color' => array(
						'validate_callback' => 'is_string',
					),
					'price' => array(
						'validate_callback' => 'is_string',
					),
				),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/products/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/schema', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get the dealer
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_dealer( $request ) {
		$current_user = wp_get_current_user();
		$user = $current_user->to_array();
		$metas = get_user_meta($current_user->ID);
		$meta = array();
		foreach ($metas as $key => $value) {
			if(!empty($value) && is_array($value) && count($value) == 1){
				$meta[$key] = $value[0];
			}else if(!empty($value)){
				$meta[$key] = $value;
			}
		}
		$user['meta'] = $meta;
		return new WP_REST_Response( $user, 200 );
	}

	/**
	 * Get a collection of items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$current_user = wp_get_current_user();
		$args         = [
			"status"   => "publish",
			"type"     => [ "simple" ],
			"owner_id" => $current_user->ID,
		];
		$items        = wc_get_products( $args );
		$data         = array();
		foreach ( $items as $item ) {
			$itemdata = $this->prepare_item_for_response( $item, $request );
			$data[]   = $this->prepare_response_for_collection( $itemdata );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id   = $request->get_param( 'id' );
		$item = wc_get_product( $id );
		$data = $this->prepare_item_for_response( $item, $request );
		if ( ! empty( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		} else {
			return new WP_Error( 404, __( 'Cannot find product with given ID' ) );
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
	public function create_item( $request ) {
		$user           = wp_get_current_user();
		$params         = $request->get_params();
		$parent_id      = $params["parent_id"];
		$parent_product = wc_get_product( $parent_id );
		$color          = $params["color"];
		$size           = $params["size"];
		$price          = $params["price"];
		$region         = get_user_meta( $user->ID, "region", true );
		$city           = get_user_meta( $user->ID, "city", true );
		if ( empty( $parent_product ) || empty( $color ) || empty( $size ) || empty( $price ) || ! is_string( $price ) || ! ( $parent_product instanceof WC_Product_Grouped ) ) {
			return new WP_Error( 400, __( 'Cannot create product with given information' ) );
		}
		$attrs  = Array(
			'pa_color' => Array(
				'name'        => 'color',
				'value'       => $color,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			),
			'pa_size' => Array(
				'name'        => 'size',
				'value'       => $size,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			),
			'pa_region' => Array(
				'name'        => 'region',
				'value'       => $region,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			),
			'pa_city' => Array(
				'name'        => 'city',
				'value'       => $city,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			),
		);
		$sku        = $parent_product->get_sku() . "-" . sanitize_title( $user->user_login ) . "-" . sanitize_title( $color ) . "-" . sanitize_title( $size );
		$title      = $parent_product->get_title() . " - " . $user->user_login;
		$data       = $parent_product->get_data();
		$data['id'] = null;

		$product = new WC_Product_Simple();
		$product->set_props( $data );
		$product->set_sku( $sku );
		$product->set_name( $title );
		$product->set_price( $price );
		$pid = $product->save();

		update_post_meta($pid, "_product_attributes", $attrs);

		// colore, size, prezzo, region, city, sku calcolato (sku_original-username_dealer-colore-taglia), nome calcolato (nome_original - username_dealer)
		$item = $product;
		$data = $this->prepare_item_for_response( $item, $request );
		if ( ! empty( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		} else {
			return new WP_Error( 404, __( 'Cannot find product with given ID' ) );
		}
	}

	/**
	 * Edit a specified item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function edit_item($request) {
		$user           = wp_get_current_user();
		$params         = $request->get_params();
		$id             = $params["id"];
		$product        = wc_get_product( $id );
		$author         = get_post_field("post_author", $id, 'raw');
		$color          = $params["color"];
		$size           = $params["size"];
		$price          = $params["price"];
		$region         = get_user_meta( $user->ID, "region", true );
		$city           = get_user_meta( $user->ID, "city", true );
		$attrs = [
			'pa_region' => Array(
				'name'        => 'region',
				'value'       => $region,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			),
			'pa_city' => Array(
				'name'        => 'city',
				'value'       => $city,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			),
		];
		if (empty($product) || $author !== $user->ID) {
			return new WP_Error( 404, __( 'Cannot find product with given ID associated with the current dealer.' ) );
		}
		if (!empty($color)) {
			$attrs['pa_color'] = Array(
				'name'        => 'color',
				'value'       => $color,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			);
		}

		if (!empty($size)) {
			$attrs['pa_size'] = Array(
				'name'        => 'size',
				'value'       => $size,
				'is_visible'  => '1',
				'is_taxonomy' => '1'
			);
		}

		if (!empty($price) && is_string($price)) {
			$product->set_price($price);
		}

		$product->save();

		update_post_meta($id, "_product_attributes", $attrs);

		$item = $product;
		$data = $this->prepare_item_for_response( $item, $request );
		if ( ! empty( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		} else {
			return new WP_Error( 404, __( 'Cannot find product with given ID' ) );
		}
	}


	/**
	 * delete an items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_items( $request ) {
		$user           = wp_get_current_user();
		$id         = $request->get_param("id");
		$product = wc_get_product( $id );
		$author         = get_post_field("post_author", $id, 'raw');
		if(!empty($product) && $author === $user->ID) {
			$product->delete();
			return new WP_REST_Response(["status" => "OK"], 200);
		}
		return new WP_Error( 404, __( 'Cannot delete product with given ID' ) );
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
	) {
		return current_user_can( 'create_product' );
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
	) {
		return $this->get_items_permissions_check( $request );
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
	) {
		return $this->get_items_permissions_check( $request );
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
	) {
		return $this->get_items_permissions_check( $request );
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
	) {
		return $this->get_items_permissions_check( $request );
	}


	/**
	 * Check if a given request has access to delete an item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public
	function delete_item_permissions_check($request) {
		return $this->get_items_permissions_check( $request );
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
	) {
		if (!empty($item) && $item instanceof WC_Product) {
			$data = $item->get_data();
			$image = null;
			if(!empty($data['image_id']) && ($img_id = intval($data['image_id'])) && ($img = wp_get_attachment_url($img_id))){
				$image = $img;
			}
			$data['image'] = $image;
			$gallery = array();
			if (!empty($data['gallery_image_ids']) && is_array($data['gallery_image_ids']) && ($imgs = $data['gallery_image_ids'])) {
				foreach ($imgs as $img) {
					if(!empty($img) && ($img_id = intval($img)) && ($url = wp_get_attachment_url($img_id))){
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
	function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'description'       => 'Limit results to those matching a string.',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
