<?php

// (C) 2022 Dmitry Y. Lepikhin 


class ControllerExtensionModuleAtags extends Controller{

    public function index(){
        
        $data = [];

        if( isset($this->request->get['product_id']) ){
            
            $product_id = (int)$this->request->get['product_id'];
            
            $sql = "SELECT d.`tag_id` as `tag_id`, d.`short_name` as `short_name`, t.`category_id` as `category_id` FROM ( SELECT DISTINCT p.`tag_id` as `tag_id`, t.`category_id` as `category_id` FROM ( SELECT `tag_id` FROM `".DB_PREFIX."atags_stores` WHERE `store_id` = '".(int)$this->config->get('config_store_id')."' ) as s left join  `".DB_PREFIX."atags_tags_to_products` p on (s.tag_id = p.tag_id) left join ( SELECT `tag_id`, `category_id`, `status` FROM `".DB_PREFIX."atags_tags`) t ON (p.tag_id = t.tag_id)  WHERE t.status = 1 AND p.`product_id` = '".$product_id."' ) as t left join ( SELECT `tag_id` , `short_name` FROM `".DB_PREFIX."atags_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')." ) as d on ( t.tag_id = d.tag_id ) WHERE d.`short_name` <> '' AND d.`short_name` is not NULL";
            $data['atags'] = $this->db->query( $sql )->rows;

            foreach( $data['atags'] as &$v ){
                if( $v['category_id'] ){
                    $category_id = $v['category_id'];
                    $path = $v['category_id'];
                    while( ( $res = $this->db->query( 'SELECT `parent_id` FROM `'.DB_PREFIX.'category` WHERE `category_id` = '. (int)$category_id )->row ) && $res['parent_id'] ){
                        $category_id = $res['parent_id'];
                        $path = $res['parent_id']. '_' . $path;
                    }
                    $v['href'] = $this->url->link('product/category', 'path='. $path .'&atags=' . (int)$v['tag_id']);
                }else
                    $v['href'] = $this->url->link('product/category', 'atags=' . (int)$v['tag_id']);
            }
        
        }elseif( $this->registry->get('products_atags') ){
            
            $sql = "SELECT d.`tag_id` as `tag_id`, d.`short_name` as `short_name`, t.`category_id` as `category_id` FROM ( SELECT DISTINCT p.`tag_id` as `tag_id`, t.`category_id` as `category_id` FROM ( SELECT `tag_id` FROM `".DB_PREFIX."atags_stores` WHERE `store_id` = '".(int)$this->config->get('config_store_id')."' ) as s left join  `".DB_PREFIX."atags_tags_to_products` p on (s.tag_id = p.tag_id) left join ( SELECT `tag_id`, `category_id`, `status` FROM `".DB_PREFIX."atags_tags`) t ON (p.tag_id = t.tag_id)  WHERE ". ( isset($this->request->get['atags']) && (int)$this->request->get['atags'] ?  " p.`tag_id` <> ". (int)$this->request->get['atags'] ." AND " : "" ) ." t.status = 1 AND p.`product_id` in (". implode( ',' , $this->registry->get('products_atags') ) .") ) as t left join ( SELECT `tag_id` , `short_name` FROM `".DB_PREFIX."atags_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')." ) as d on ( t.tag_id = d.tag_id ) WHERE d.`short_name` <> '' AND d.`short_name` is not NULL";
            $data['atags'] = $this->db->query( $sql )->rows;

            foreach( $data['atags'] as &$v ){
                if( $v['category_id'] ){
                    $category_id = $v['category_id'];
                    $path = $v['category_id'];
                    while( ( $res = $this->db->query( 'SELECT `parent_id` FROM `'.DB_PREFIX.'category` WHERE `category_id` = '. (int)$category_id )->row ) && $res['parent_id'] ){
                        $category_id = $res['parent_id'];
                        $path = $res['parent_id']. '_' . $path;
                    }
                    $v['href'] = $this->url->link('product/category', 'path='. $path .'&atags=' . (int)$v['tag_id']);
                }else
                    $v['href'] = $this->url->link('product/category', 'atags=' . (int)$v['tag_id']);
            }
            
        }
        
        if( $data )
            return $this->load->view('extension/module/atags', $data);

    }

}

?>