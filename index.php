<?php

/*
Plugin Name: Woocommerce Plugin Bikeroom Finder Dashboard
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A plugin to instantiate API connection to the dashboard..
Version: 1.0
Author: LOUD s.r.l.
Author URI: http://loudsrl.com
License: GPL
*/

add_action("rest_api_init", "finder_api_init");
add_filter('woocommerce_product_data_store_cpt_get_products_query', 'filter_products_by_brand_and_author', 10, 2);

function finder_api_init() {
	include_once __DIR__ . "/controllers/BrandsFinderController.php";
	include_once __DIR__ . "/controllers/ProductsFinderController.php";
	include_once __DIR__ . "/controllers/DealerFinderController.php";
	(new BrandsFinderController())->register_routes();
	(new ProductsFinderController())->register_routes();
	(new DealerFinderController())->register_routes();
}

function filter_products_by_brand_and_author($query, $query_vars) {
	if (!empty($query_vars['brand_ids'])) {
		$query['tax_query'][] = array(
			'taxonomy' => 'brand',
			'field'    => 'term_id',
			'terms'    => $query_vars['brand_ids'],
			'operator' => 'IN',
		);
	}
	if (!empty($query_vars['owner_id'])) {
		$query['author'] = $query_vars['owner_id'];
	}
	return $query;
}
