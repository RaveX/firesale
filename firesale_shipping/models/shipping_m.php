<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
* This file is part of FireSale, a PHP based eCommerce system built for
* PyroCMS.
*
* Copyright (c) 2013 Moltin Ltd.
* http://github.com/firesale/firesale
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*
* @package firesale/shipping
* @author FireSale <support@getfiresale.org>
* @copyright 2013 Moltin Ltd.
* @version master
* @link http://github.com/firesale/firesale
*
*/

class Shipping_m extends MY_Model
{

    public function get_option_by_id($id)
    {
        $query = $this->db->where('id', $id)->get('firesale_shipping');
        if ( $query->num_rows() ) {
            $result = $query->row_array();
            $result = array_merge($result, $this->calculate_tax($result['id'], $result['price']));
            return $result;
        }

        return '0.00';
    }

    public function get_options($where = 'status = 1')
    {
        // Select shipping options
        $params  = array(
            'stream'    => 'firesale_shipping',
            'namespace' => 'firesale_shipping',
            'order_by'  => 'price',
            'sort'      => 'asc',
            'where'     => $where
        );

        // Get options
        return $this->streams->entries->get_entries($params);
    }

    public function calculate_methods($cart)
    {

        // Variables
        $total_weight  = 0;
        $total_value   = 0;
        $total_options = array();

        // Get total weight and value
        foreach ($cart AS $item) {
            $total_weight += ( $item['weight'] * $item['qty'] );
            $total_value  += ( $item['price']  * $item['qty'] );
        }

        // Get options
        $options = $this->pyrocache->model('shipping_m', 'get_options', array(), $this->firesale->cache_time);

        // Loop options and perform checks
        foreach ($options['entries'] AS $option) {
            if ( $this->check_methods($option, $total_weight, $total_value) ) {
                $option = array_merge($option, $this->calculate_tax($option['tax_band']['id'], $option['price']));
                $total_options[] = $option;
            }
        }

        // Return options
        return $total_options;
    }

    public function check_methods($method, $weight, $price)
    {
        // Check pricing
        if ($method['price_min'] != "" && $price < $method['price_min']) {
            return false;
        }

        if ($method['price_max'] != "" && $price > $method['price_max']) {
            return false;
        }

        // Check weight
        if ($method['weight_min'] != "" && $weight != "" && $weight < $method['weight_min']) {
            return false;
        }

        if ($method['weight_max'] != "" && $weight != "" && $weight > $method['weight_max'] ) {
            return false;
        }

        return true;
    }

    private function calculate_tax($tax_band = null, $price = "0.00")
    {
        $formatted['price_pre_tax'] = $price;
        $formatted['price'] = $price;
        if (isset($tax_band)) {
            // Calculate tax for this shipping method
            $tax  = $this->pyrocache->model('taxes_m', 'get_percentage', array($tax_band), $this->firesale->cache_time);
            $tax  = ( 100 + $tax ) / 100;

            $formatted['price_pre_tax'] = $price;
            $formatted['price'] = format_currency(($tax * $price), false, true, false, false);
        }	
        return $formatted;
    }

}
