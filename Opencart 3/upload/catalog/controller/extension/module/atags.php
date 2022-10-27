<?php

// (C) 2022 Dmitry Y. Lepikhin 


class ControllerExtensionModuleAtags extends Controller{

    public function index(){
        
        $data = [];

        if( $this->request->get['route'] != 'extension/module/atags' ){

            if( isset($this->request->get['product_id']) ){
                
                $product_id = (int)$this->request->get['product_id'];
                
                $sql = "SELECT d.`tag_id` as `tag_id`, d.`short_name` as `short_name` FROM ( SELECT DISTINCT p.`tag_id` as `tag_id` FROM ( SELECT `tag_id` FROM `".DB_PREFIX."atags_stores` WHERE `store_id` = '".(int)$this->config->get('config_store_id')."' ) as s left join  `".DB_PREFIX."atags_tags_to_products` p on (s.tag_id = p.tag_id) left join ( SELECT `tag_id`, `status` FROM `".DB_PREFIX."atags_tags`) t ON (p.tag_id = t.tag_id)  WHERE t.status = 1 AND p.`product_id` = '".$product_id."' ) as t left join ( SELECT `tag_id` , `short_name` FROM `".DB_PREFIX."atags_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')." ) as d on ( t.tag_id = d.tag_id ) WHERE d.`short_name` <> '' AND d.`short_name` is not NULL";
                $data['tags'] = $this->db->query( $sql )->rows;

                foreach( $data['tags'] as &$v ){
                    $v['href'] = $this->url->link('product/category', 'atags=' . (int)$v['tag_id']);
                }
            
            }elseif( isset($this->request->get['atags']) ){
                
                $atags = (int)$this->request->get['atags'];
                
                $sql = "SELECT d.`tag_id` as `tag_id`, d.`short_name` as `short_name` FROM ( SELECT DISTINCT p.`tag_id` as `tag_id` FROM ( SELECT `tag_id` FROM `".DB_PREFIX."atags_stores` WHERE `store_id` = '".(int)$this->config->get('config_store_id')."' ) as s left join  `".DB_PREFIX."atags_tags_to_products` p on (s.tag_id = p.tag_id) left join ( SELECT `tag_id`, `status` FROM `".DB_PREFIX."atags_tags`) t ON (p.tag_id = t.tag_id)  WHERE t.status = 1 AND p.`product_id` in ( SELECT `product_id` FROM  `".DB_PREFIX."atags_tags_to_products` WHERE `tag_id` = ".$atags." ) AND p.`tag_id` <> ".$atags."  ) as t left join ( SELECT `tag_id` , `short_name` FROM `".DB_PREFIX."atags_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')."  ) as d on ( t.tag_id = d.tag_id ) WHERE d.`short_name` <> '' AND d.`short_name` is not NULL ";
                $data['tags'] = $this->db->query( $sql )->rows;

                foreach( $data['tags'] as &$v ){
                    $v['href'] = $this->url->link('product/category', 'atags=' . (int)$v['tag_id']);
                }

            }elseif( isset($this->request->get['path']) ){

                $category_id = explode('_', $this->request->get['path'] );
                $category_id = (int)array_pop($category_id);
                
                $sql = "SELECT DISTINCT d.`tag_id` as `tag_id`, d.`short_name` as `short_name` FROM ( SELECT DISTINCT p.`tag_id` as `tag_id` FROM ( SELECT `tag_id` FROM `".DB_PREFIX."atags_stores` WHERE `store_id` = '".(int)$this->config->get('config_store_id')."' ) as s left join `".DB_PREFIX."atags_tags_to_products` p on (s.tag_id = p.tag_id) left join ( SELECT `tag_id`, `status` FROM `".DB_PREFIX."atags_tags`) t ON (p.tag_id = t.tag_id)  WHERE t.status = 1 AND p.`product_id` in ( SELECT `product_id` FROM `".DB_PREFIX."product_to_category` WHERE `category_id` = '".$category_id."') ) as t left join ( SELECT `tag_id` , `short_name` FROM `".DB_PREFIX."atags_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')."  ) as d on ( t.tag_id = d.tag_id ) WHERE d.`short_name` <> '' AND d.`short_name` is not NULL";
                $data['tags'] = $this->db->query( $sql )->rows;

                foreach( $data['tags'] as &$v ){
                    $v['href'] = $this->url->link('product/category', 'atags=' . (int)$v['tag_id']);
                }
            
            }
            
            if( $data )
                return $this->load->view('extension/module/atags', $data);
            
        } else {

            $this->load->controller('product/category');

        }

    }

}

?>