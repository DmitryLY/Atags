<?php
// (C) 2022 Dmitry Y. Lepikhin 

class ModelExtensionModuleAtags extends Model{
    
    public function createTables(){

        $sqlQ = 'create TABLE IF NOT EXISTS `'.DB_PREFIX.'atags_tags` ( 
            `tag_id` int PRIMARY KEY AUTO_INCREMENT,
            `status` boolean DEFAULT false,
            `category_id` int DEFAULT NULL,
            `bound_values` JSON DEFAULT NULL
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
        if( !$this->db->query($sqlQ) )$data['warning'] = $sqlQ.' - Не удалось создать таблицу atags_tags';

        $sqlQ = 'create TABLE IF NOT EXISTS `'.DB_PREFIX.'atags_stores` ( 
            `tag_id` int,
            `store_id` int NOT NULL,
            PRIMARY KEY (`tag_id`,`store_id`),
            KEY `store_id` (`store_id`)
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
        if( !$this->db->query($sqlQ) )$data['warning'] = $sqlQ.' - Не удалось создать таблицу atags_tags';

        $sqlQ = 'create TABLE IF NOT EXISTS `'.DB_PREFIX.'atags_description` ( 
            `tag_id` int,
            `language_id` int NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `short_name` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(255) NOT NULL,
            `meta_title` VARCHAR(255) NOT NULL,
            `meta_description` VARCHAR(255) NOT NULL,
            `header_h1` VARCHAR(255) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`tag_id`,`language_id`),
            KEY `language_id` (`language_id`)
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ';
        if( !$this->db->query($sqlQ) )$data['warning'] = $sqlQ.' - Не удалось создать таблицу atags_tags';

        $sqlQ = 'create TABLE IF NOT EXISTS `'.DB_PREFIX.'atags_tags_to_products` ( 
            `tag_id` int NOT NULL,
            `product_id` int NOT NULL,
            `manually`  boolean DEFAULT false,
            PRIMARY KEY (`tag_id`,`product_id`),
            KEY `product_id` (`product_id`)
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
        if( !$this->db->query($sqlQ) )$data['warning'] = $sqlQ.' - Не удалось создать таблицу atags_tags';

    }

    private function getStores($assoc = false){

        $this->load->model('setting/store');
        $stores = array();

        if( $assoc )
            $stores[0] =  $this->language->get('text_default');
        else
            $stores[] = array(
                'store_id' => 0,
                'name'     => $this->language->get('text_default')
            );
        
        foreach ($this->model_setting_store->getStores() as $store) {
            if( $assoc )
                $stores[ $store['store_id'] ] = $store['name'] ;
            else
                $stores[] = array(
                    'store_id' => $store['store_id'],
                    'name'     => $store['name']
                );
        }

        return $stores;
    }

    private function addslashes( &$data ){
        foreach( $data as &$v )
            if( is_array( $v ) )
                $this->addslashes( $v );
            else
                $v = addslashes( $v );
    }

    private function check_criterions( $tag_id , $category_id , &$bound_values ){
        
        $categories = [];

        if( (int)$category_id )
            $categories = [(int)$category_id];
        elseif( $bound_values['categories'] )
            foreach( $bound_values['categories'] as $v )
                $categories[] = $v['id'];
        
        if( !$categories ) return;

        for( $i = 0 ; $i < count($categories) ; $i++ ){
            if( $res = $this->db->query("SELECT `category_id` FROM `".DB_PREFIX."category` WHERE `parent_id` = ". $categories[$i] )->rows )
                foreach( $res as $v )
                    $categories[] = (int)$v['category_id'];
        }

        $products = [];

        foreach( $this->db->query(" SELECT `product_id` FROM `".DB_PREFIX."product_to_category` WHERE `category_id` in (". implode( ',', $categories  ) .") ")->rows as $v )
            $products[] = $v['product_id'];

        if( !$products ) {
            $bound_values['options'] = $bound_values['attributes'] = [];   
            return;
        }

            foreach( $bound_values['attributes'] as $k => &$v ){
                
                if( !$this->db->query( " SELECT `product_id` FROM `".DB_PREFIX."product_attribute` WHERE `attribute_id` = ". $v['id']. " AND  `product_id` in (". implode( ',' , $products ) .") LIMIT 1 ")->row )
                    unset($bound_values['attributes'][$k]);
                elseif( $v['values'] ){
                    $values = [];

                    foreach( $v['values'] as $kk => &$vv )
                        if( !$this->db->query( " SELECT `text` FROM `".DB_PREFIX."product_attribute` WHERE `attribute_id` = ". $v['id'] . " AND `language_id` = ". (int)$this->config->get('config_language_id') ." AND `text` = '". $vv['value'] ."' AND `product_id` in (". implode( ',' , $products ) .")  LIMIT 1" )->row )
                            unset($v['values'][$kk]);

                    $v['values'] = array_values($v['values']);
                }

            }

            $bound_values['attributes'] = array_values($bound_values['attributes']);

            foreach( $bound_values['options'] as $k => &$v ){

                if( !$this->db->query( " ( SELECT `product_id` FROM `".DB_PREFIX."product_option` WHERE `option_id` = ". $v['id']. " AND `product_id` in (". implode( ',' , $products ) .")  LIMIT 1 ) UNION ( SELECT `product_id` FROM `".DB_PREFIX."product_option_value` WHERE `option_id` = ". $v['id']. " AND `product_id` in (". implode( ',' , $products ) .") LIMIT 1 ) ")->row ){
                    unset($bound_values['options'][$k]);
                }elseif( $v['values'] ){
                    $values = [];
                    
                    foreach( $v['values'] as $kk => &$vv )
                        if( !$this->db->query( " ( SELECT DISTINCT `value` FROM `".DB_PREFIX."product_option` WHERE option_id = ". $v['id'] . " AND `product_id` in (". implode( ',' , $products ) .") AND  `value` = '".$vv['value']."'  LIMIT 1 ) UNION ( SELECT DISTINCT `name` as `value` FROM ( SELECT * FROM `".DB_PREFIX."option_value_description` WHERE `option_id` = ". $v['id']. " AND `language_id` = ". (int)$this->config->get('config_language_id') ." AND `name` = '".$vv['value']."' ) pov LEFT JOIN ( SELECT * FROM `".DB_PREFIX."product_option_value` ovd WHERE `product_id` in (". implode( ',' , $products ) .") ) ovd using(option_id) LIMIT 1)" )->row )
                            unset($v['values'][$kk]);
                    
                    $v['values'] = array_values($v['values']);
                }

            }

            $bound_values['options'] = array_values($bound_values['options']);
            
    }

    private function bind_products( $tag_id , $category_id = 0 , $bound_values , $bound_products ){

        $tag_id = (int)$tag_id;

        $bound_products_sql = [];

        $categories = [];

            if( isset( $bound_values['categories'] ) && $bound_values['categories'] ){
                $categories = [];

                foreach( $bound_values['categories'] as $v ){
                    $categories[] = $v['id'];
                }
            }elseif( $category_id ){
                $categories = [(int)$category_id];
            }

            if( $categories ){

                for( $i = 0 ; $i < count($categories) ; $i++ ){
                    if( $res = $this->db->query("SELECT `category_id` FROM `".DB_PREFIX."category` WHERE `parent_id` = ". $categories[$i] )->rows )
                        foreach( $res as $v )
                            $categories[] = (int)$v['category_id'];
                }

                $bind_products = [];
                $sql = "SELECT `product_id` FROM `".DB_PREFIX."product_to_category` WHERE `category_id` in (". implode(',' , $categories) .")" ;
                foreach( $this->db->query( $sql )->rows as $v )
                    $bind_products[] = $v['product_id'];
            }

            if( $category_id && $bound_products ){
                $bound_products = array_intersect( $bound_products , $bind_products );
            }

            if( $category_id && !$bound_values['attributes'] && !$bound_values['options'] )
                $bind_products = [];
            

            if( ( !isset($bind_products) || $bind_products ) && isset( $bound_values['attributes'] ) && $bound_values['attributes'] ){
                if( !isset($bind_products) ) $bind_products = [];
                
                foreach( $bound_values['attributes'] as $v ){
                    $values = [];
                    foreach( $v['values'] as $vv ){
                        $values[] = " `text` LIKE  '". ( $vv['strict'] ? $vv['value'] : "%".$vv['value']."%") ."' ";
                    }

                    $sql = "SELECT `product_id` FROM `".DB_PREFIX."product_attribute` WHERE `attribute_id` = '".(int)$v['id']."' ". ( $values ? " AND ". implode( ' OR ' , $values )  : "" ) ;
                    $bind_products_pre = [];

                    foreach( $this->db->query( $sql )->rows as $v )
                        $bind_products_pre[] = $v['product_id'];

                        if( !$bind_products_pre ) {
                            $bind_products = [];
                            break;
                        }
                        
                        $bind_products = $bind_products ? array_intersect( $bind_products , $bind_products_pre ) : $bind_products_pre ;
                        
                        if( !$bind_products ) break;
                }
            }

            if( ( !isset($bind_products) || $bind_products ) && isset( $bound_values['options'] ) && $bound_values['options'] ){
                if( !isset($bind_products) ) $bind_products = [];

                if( !function_exists('where_arr') ){
                    function where_arr($v){
                        $values = [ 'names' => [] , 'values' => [] ];
                        foreach( $v['values'] as $vv ){
                            $values['names'][] = " `name` LIKE  '". ( $vv['strict'] ? $vv['value'] : "%".$vv['value']."%") ."' ";
                            $values['values'][] = " `value` LIKE  '". ( $vv['strict'] ? $vv['value'] : "%".$vv['value']."%") ."' ";
                        }
                        return $values;
                    }
                }
                
                foreach( $bound_values['options'] as $v ){
                    $values = where_arr( $v );

                    $sql = " ( SELECT `product_id` FROM `".DB_PREFIX."product_option_value` WHERE `option_id` = '".(int)$v['id']."' ". ( $values['names'] ? " AND `option_value_id` in ( SELECT `option_value_id` FROM `".DB_PREFIX."option_value_description` WHERE ". implode( ' OR ' , $values['names'] ) .") " : "" )." )  UNION ( SELECT `product_id` FROM `".DB_PREFIX."product_option` WHERE `option_id` = '".(int)$v['id']."' ". ( $values['values'] ? " AND  (". implode( ' OR ' , $values['values'] ) .")" : "" ).") " ;
                    $bind_products_pre = [];
                    
                    foreach( $this->db->query( $sql )->rows as $v )
                        $bind_products_pre[] = $v['product_id'];

                    if( !$bind_products_pre ) {
                        $bound_products = [];
                        break;
                    }
                    
                    $bind_products = $bind_products ? array_intersect( $bind_products , $bind_products_pre ) : $bind_products_pre ;
                    
                    if( !$bind_products ) break;
                }
            }


            if( isset( $bind_products ) )
                foreach( $bind_products as $v )
                    $bound_products_sql[] = "('". $tag_id ."', '". $v ."', 0 )";


        $sql = "DELETE FROM `".DB_PREFIX."atags_tags_to_products` WHERE `tag_id` = '".$tag_id."'  ". ( $bound_products ? " AND `product_id` not in (". implode(',',$bound_products) .") " : '' ) ;
        $this->db->query( $sql );

        if( isset( $bound_products ) )
            foreach( $bound_products as $v )
                $bound_products_sql[] = "('". $tag_id ."', '". $v ."', 1 )";

        if( $bound_products_sql ){

            $sql = "INSERT IGNORE INTO `".DB_PREFIX."atags_tags_to_products` ( `tag_id` , `product_id` , `manually` ) VALUES ". implode( ',' , $bound_products_sql ) ;
            $this->db->query( $sql );
            
            $sql = "SELECT p.`product_id` as `product_id` , p.`model` as `model` , pd.`name` as `name`, t.`manually` as `manually` FROM ( SELECT `product_id` , `manually` FROM `".DB_PREFIX."atags_tags_to_products` WHERE `tag_id` = '".$tag_id."' ) as t left join ( SELECT `product_id` , `model` FROM `".DB_PREFIX."product` ) as p on (p.product_id = t.product_id) left join ( SELECT `product_id`, `name` FROM `".DB_PREFIX."product_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')."  ) as pd on (p.product_id = pd.product_id)  ORDER BY t.`manually` DESC";
            $bound_products = $this->db->query( $sql )->rows;

        }

        return $bound_products;

    }

    public function init(){

        $json = [];

        $this->load->model('localisation/language');
        $json['languages'] = $this->model_localisation_language->getLanguages();

        $json['language_current'] = (int)$this->config->get('config_language_id');

        $json['stores'] = $this->getStores();

        $json['language_'] = $this->load->language('extension/module/atags');

        $json['settings'] = [];
        $json['settings']['status'] = $this->config->get('module_atags_status');

        if( !$json['languages'] || !$json['stores'] || !$json['settings'] )
            $json = [];

        return $json;

    }

    public function get_tab(){

        $tag_id = (int)$this->request->post['tag_id'];

        $sql = "SELECT * FROM `".DB_PREFIX."atags_tags` WHERE `tag_id` = '".$tag_id."'";
        $json = $this->db->query( $sql )->row;
        $category = $this->db->query( "SELECT `category_id` as `id` , `name` FROM `".DB_PREFIX."category_description` WHERE `category_id` = '".$json['category_id']."'" )->row;

        if( $category )
            $json['category'] = $category;

        if(isset($json['tag_id'])){

            $json['bound_values'] = json_decode( $json['bound_values']  );

            $sql = "SELECT `store_id` FROM `".DB_PREFIX."atags_stores` WHERE `tag_id` = '".$json['tag_id']."' ";
            
            $json['stores_id'] = [];
            foreach( $this->db->query( $sql )->rows as $v )
                $json['stores_id'][] = $v['store_id'];


            $sql = "SELECT * FROM (SELECT `language_id`,`code`, `name` as `name_lang`, ".$json['tag_id']." as `tag_id` FROM `".DB_PREFIX."language`) as l left join ( SELECT * FROM  `".DB_PREFIX."atags_description` WHERE `tag_id` = '".$json['tag_id']." AND `language_id` is not NULL')  as d USING ( tag_id , language_id ) ";
            $json['langs'] = $this->db->query( $sql )->rows;
            
            $stores = $this->getStores();

            foreach( $json['langs'] as &$lang ){
                $lang['seo_keyword'] = $stores;

                foreach( $lang['seo_keyword'] as &$l ){
                    $l['keyword'] = '';
                }

                foreach( $this->db->query(  $json['test'] = " SELECT `store_id`, `keyword` FROM `".DB_PREFIX."seo_url` WHERE `query` = 'atags=".$json['tag_id']."' AND `language_id` = ".(int)$lang['language_id']." " )->rows  as &$v ){
                    foreach( $lang['seo_keyword'] as &$l ){
                        $l['keyword'] = ( $l['store_id'] == $v['store_id'] ? $v['keyword'] : '' );
                    }
                }
            }
            
            $sql = "SELECT p.`product_id` as `product_id` , p.`model` as `model` , pd.`name` as `name`, t.`manually` as `manually` FROM ( SELECT `product_id` , `manually` FROM `".DB_PREFIX."atags_tags_to_products` WHERE `tag_id` = '".$json['tag_id']."' ) as t left join ( SELECT `product_id` , `model` FROM `".DB_PREFIX."product` WHERE `product_id`) as p on (p.product_id = t.product_id) left join ( SELECT `product_id`, `name` FROM `".DB_PREFIX."product_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')." ) as pd on (p.product_id = pd.product_id)  ORDER BY t.`manually` DESC";
            $json['bound_products'] = $this->db->query( $sql )->rows;

        }

        return $json;

    }

    public function delete_tag(){

        $tag_id = (int)$this->request->post['tag_id'];

        $sql = "DELETE FROM `".DB_PREFIX."atags_tags` WHERE `tag_id` = '".$tag_id."'";
        $this->db->query( $sql );

        $sql = "DELETE FROM `".DB_PREFIX."atags_stores` WHERE `tag_id` = '".$tag_id."'";
        $this->db->query( $sql );

        $sql = "DELETE FROM `".DB_PREFIX."atags_description` WHERE `tag_id` = '".$tag_id."'";
        $this->db->query( $sql );

        $sql = "DELETE FROM `".DB_PREFIX."atags_tags_to_products` WHERE `tag_id` = '".$tag_id."'";
        $this->db->query( $sql );

        $sql = "DELETE FROM `".DB_PREFIX."seo_url` WHERE `query` = 'atags=".$tag_id."'";
        $this->db->query( $sql );

    }

    public function refresh_binding(){

        $sql = "SELECT `tag_id`, `category_id` , `bound_values` FROM `".DB_PREFIX."atags_tags` ";

        foreach( $this->db->query($sql)->rows as $v ){
            $sql = "SELECT `product_id` FROM `".DB_PREFIX."atags_tags_to_products` WHERE `tag_id` = ".$v['tag_id']." AND `manually` = 1 ";
            $bound_products = [];

            foreach( $this->db->query($sql)->rows as $p )
                $bound_products[] = $p['product_id'];

            $this->bind_products( $v['tag_id'] , $v['category_id'] , json_decode(  $v['bound_values'] , true, 512, JSON_OBJECT_AS_ARRAY  ) , $bound_products );
        }

        return ['success' => true];

    }

    public function save_tab(){

        $data = json_decode(  $_POST['data'] , true, 512, JSON_OBJECT_AS_ARRAY  );
        $json = ['abc' => 123];

        if( $data  ){

        $this->check_criterions( (int)$data['tag_id'] , (int)$data['category_id'] , $data['bound_values'] );

        $clean_bound_values = $json['bound_values'] = $data['bound_values'];
        
        $this->addslashes($data);
        

            foreach( $data['langs'] as $v ){ 

                $fields_required = [ 'name' => $v['name'] , 'short_name' => $v['short_name'] , 'full_name' => $v['full_name'] , 'meta_title' => $v['meta_title'] ];

                $sql_1 = [];
                $sql_2 = [];

                foreach( $fields_required as $kf => &$vf )
                    if( !$vf ){
                        $json['errors']['langs'][(int)$v['language_id']][$kf] = "Не заполнено!";
                        unset( $fields_required[$kf] );
                    }else{
                        $sql_1[] = "IF( `".$kf."` = '".$vf."' , 1 , 0 ) as `".$kf."`";
                        $sql_2[] = " `".$kf."` = '". $vf ."' ";
                    }

                
                if( $sql_1 && $sql_2 )
                foreach( $this->db->query("SELECT ". implode(' , ', $sql_1)." FROM `".DB_PREFIX."atags_description` WHERE ".( (int)$data['tag_id'] ? " `tag_id` <> '".(int)$data['tag_id']."' AND  " : "" )." `language_id` = '".(int)$v['language_id']."' AND ( ". implode(' OR ', $sql_2)." ) ")->rows as $e )
                    foreach( $e as $ke => $ve )
                        if( $ve )
                            $json['errors']['langs'][(int)$v['language_id']][$ke] = 'Не уникальное!';

                if( $v['seo_keyword'] ){
                    foreach( $v['seo_keyword'] as $sk ){
                        if( empty( $sk['keyword'] ) ) continue;
                        $sql = " SELECT * FROM `".DB_PREFIX."seo_url` WHERE `language_id` = ".(int)$v['language_id']." AND `keyword` = '".$sk['keyword']."' AND `store_id` = ".(int)$sk['store_id']." ". ( (int)$data['tag_id'] ? "AND `query` <> 'atags=".(int)$data['tag_id']."' " : "" );
                        if( $this->db->query( $sql )->num_rows ){
                            $json['errors']['langs'][(int)$v['language_id']]['seo_keyword'][(int)$sk['store_id']] = '"'. $sk['keyword'] . '" - SEO ключевое слово не уникально!';
                            continue;
                        }

                    }
                }

            }

            if( !isset($json['errors']) ){

                $sql = "INSERT INTO `".DB_PREFIX."atags_tags` ( `tag_id` , `status` , `category_id` , `bound_values` ) VALUES ('". ( isset($data['tag_id']) ? $data['tag_id'] : '' ) ."', '". (int)$data['status'] ."' , '". (int)$data['category_id'] ."' , '". addslashes( json_encode( $clean_bound_values , JSON_UNESCAPED_UNICODE ) ) ."' ) ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`) , `status` = VALUES(`status`) , `bound_values` = VALUES(`bound_values`) ";

                if( $this->db->query( $sql ) && !$data['tag_id'] )
                    $data['tag_id'] = $json['tag_id'] = (int)$this->db->getLastId();

                    $sql = "DELETE FROM `".DB_PREFIX."seo_url` WHERE `query` = 'atags=".$data['tag_id']."' ";
                    $this->db->query( $sql );

                foreach( $data['langs'] as $v ){

                    if( $v['seo_keyword'] ){
                        foreach( $v['seo_keyword'] as $sk ){
                            if( empty( $sk['keyword'] ) ) continue;

                                $sql_res = $this->db->query(" SELECT `seo_url_id` FROM `".DB_PREFIX."seo_url` WHERE `language_id` = ".(int)$v['language_id']." AND `query` = 'atags=".$data['tag_id']."' AND `store_id` = ".(int)$sk['store_id']." ")->row;
                                $seo_url_id = $sql_res ? (int)$sql_res['seo_url_id'] : 0;

                                $sql = " INSERT IGNORE INTO `".DB_PREFIX."seo_url` (`seo_url_id`, `store_id`, `language_id`, `query`, `keyword`) VALUES ( ".$seo_url_id.", ".(int)$sk['store_id'].", ".(int)$v['language_id'].", 'atags=".$data['tag_id']."', '".$sk['keyword']."' ) ";
                                $this->db->query( $sql );

                        }
                    }

                    $sql = "REPLACE INTO `".DB_PREFIX."atags_description` ( `tag_id` , `language_id` , `name` , `short_name` , `full_name` , `meta_title` , `meta_description` , `header_h1` , `description` ) 
                            VALUES ('". $data['tag_id'] ."', '". $v['language_id'] ."' , '". $v['name'] ."' , '". $v['short_name'] ."' , '". $v['full_name'] ."' , '". $v['meta_title'] ."' , '". $v['meta_description'] ."' , '". $v['header_h1'] ."' , '". $v['description'] ."' ) ";
                    $this->db->query( $sql );
                }

                $sql = "DELETE FROM `".DB_PREFIX."atags_stores` WHERE `tag_id` = '".$data['tag_id']."' ";
                $this->db->query( $sql );

                if( $data['stores_id'] ){
                    foreach( $data['stores_id'] as $v )
                        $values[] = "('". $data['tag_id'] ."', '". $v ."')";

                    $sql = "INSERT IGNORE INTO `".DB_PREFIX."atags_stores` ( `tag_id` , `store_id` ) VALUES ". implode( ',' , $values ) ;
                    $this->db->query( $sql );
                }

                $json['bound_products'] = $this->bind_products( $data['tag_id'], $data['category_id'] , $data['bound_values'] , $data['bound_products'] );

            }

        }

        return $json;

    }

    public function get_tags(){

        $sql = "SELECT SQL_CALC_FOUND_ROWS t.`tag_id` as `tag_id` , td.`name` as `name` FROM ( SELECT `tag_id` FROM  `".DB_PREFIX."atags_tags`) as t inner join ( SELECT `tag_id` , `name` FROM`".DB_PREFIX."atags_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')." ) as td on ( t.tag_id = td.tag_id ) ";

        if( isset($this->request->post['request'] ) ){

            $request = json_decode( $_POST['request'] , true, 512, JSON_OBJECT_AS_ARRAY );
            $this->addslashes( $request );

            if( is_array($request) ){
                if( is_numeric($request['store_id']) ){
                    $sql .= " inner join `".DB_PREFIX."atags_stores` as s on ( t.tag_id = s.tag_id ) WHERE s.`store_id` = '".(int)$request['store_id']."' ";
                }
                
                if( trim($request['query']) !='' )
                    $sql .= ( $request['store_id'] > '-1' ? ' AND ' : " WHERE " ) . " td.`name` LIKE '%".trim($request['query'])."%' ";
            }

        }
        
        return [ 'list' => $this->db->query( $sql )->rows ];

    }

    public function save_settings(){

        $data = json_decode( $_POST['save_settings'] , true, 512, JSON_OBJECT_AS_ARRAY );
        $this->addslashes($data);

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_atags', $data);

        return ['success' => $data];

    }

    public function filter_products(){

        $data = json_decode( $_POST['filter_products'] , true, 512, JSON_OBJECT_AS_ARRAY );
        $bound_products = json_decode( $_POST['bound_products'] , true, 512, JSON_OBJECT_AS_ARRAY );
        $category_id = isset( $this->request->post['category_id'] ) ? (int)$this->request->post['category_id'] : 0 ;
        $category_value_id = is_array($data['category']) && (int)$data['category']['id'] && $data['category_value'] == $data['category']['name'] ? (int)$data['category']['id'] : '';

        $this->addslashes($data);
        $this->addslashes($bound_products);
        
        if( $data ){
            $where = [];
            $sql = '';
            $categories = [];
            $categories_ = [];

            $sql = ' SELECT p.`product_id` as `product_id` , p.`model` as `model` , pd.`name` as `name` FROM  ( SELECT `product_id` , `language_id` , `name` FROM `'.DB_PREFIX.'product_description` WHERE `language_id` = '.(int)$this->config->get('config_language_id').' '. ( $bound_products ? ' AND `product_id` not in ('.implode(',', $bound_products).') ' : '' ) .' ) pd  left join ( SELECT DISTINCT `product_id` , `model` FROM `'.DB_PREFIX.'product`  ) p on ( p.product_id = pd.product_id ) ';

            if( $category_value_id ){
                $categories = [(int)$data['category']['id']];
            }elseif( $category_id )
                $categories = [$category_id];
            elseif( $data['category_value'] ){
                if( $res = $this->db->query( " SELECT `category_id` FROM `".DB_PREFIX."category_description` WHERE `name` LIKE '%".$data['category_value']."%' " ) )
                    foreach( $res->rows as $v )
                        $categories[] = (int)$v['category_id'];
                
                if( !$categories )
                    $sql = '';
            }

            if( $sql ){

                if( $categories ){

                    for( $i = 0 ; $i < count($categories) ; $i++ ){
                        $sub_sql = '';
                        
                        if( !$category_value_id && $data['category_value'] )
                            $sub_sql = "SELECT c.`category_id` as `category_id`, ( cs.`name` LIKE '%". $data['category_value'] ."%' ) as `find` FROM `".DB_PREFIX."category` as c left join `".DB_PREFIX."category_description` as cs using( category_id ) WHERE c.`parent_id` = ". $categories[$i] ;

                        if( !$sub_sql ){
                            $sub_sql = "SELECT `category_id`, true as `find` FROM `".DB_PREFIX."category` WHERE `parent_id` = ". $categories[$i];
                        }

                        if( $res = $this->db->query( $sub_sql )->rows )
                            foreach( $res as $v ){
                                if( $v['find'] )
                                    $categories[] = (int)$v['category_id'];
                            }
                            
                    }

                    if( ( $category_id && $category_value_id && !in_array( $category_value_id , $categories ) ) ){
                        $sql = '';
                    }else{
                        if( $categories ){
                            $sql .= ' left join `'.DB_PREFIX.'product_to_category` as pc on (pc.product_id=p.product_id) ';
                            $where[] = ' pc.`category_id` in ('. implode(',', $categories) .') ';
                        }
                    }
                }

                if( trim($data['name']) != '' )
                    $where[] = ' pd.`name` LIKE \'%'. trim($data['name']) .'%\'';
                
                if( trim($data['model']) != '' )
                    $where[] = ' p.`model` LIKE \'%'. trim($data['model']) .'%\'';

                if( $data['product_id'] )
                    $where[] = ' p.`product_id` LIKE \'%'. trim($data['product_id']) .'%\'';
                

                if( $sql && $where ){
                    $sql .= ' WHERE ' . implode( ' AND ' , $where );
                    return $this->db->query( $sql )->rows;
                }
            
            }
        }

    }

    public function finder(){

        $find = isset( $this->request->post['finder'] ) ? addslashes( trim($this->request->post['finder']) ) : '';
        $name = isset( $this->request->post['name'] ) ? addslashes( trim($this->request->post['name']) ) : '';
        $value = isset( $this->request->post['value'] ) ? addslashes( trim($this->request->post['value']) ) : '';
        $what = isset( $this->request->post['what'] ) ? addslashes( trim($this->request->post['what']) ) : '';
        $id = isset( $this->request->post['id'] ) ? (int)$this->request->post['id']  : '';
        $category_id = isset( $this->request->post['category_id'] ) ? (int)$this->request->post['category_id'] : '';
        $categories = [];

        $products = [];
        $where_sql = '';
        $sql = '';
        $json = [];
        $json['finder'] = [];

        if( $find != 'categories' && isset($_POST['categories'] ) ){
            $categories = json_decode( $_POST['categories'] , true, 512, JSON_OBJECT_AS_ARRAY );
            $this->addslashes($categories);
        }

        if( $category_id || $categories ){
            if( !$categories ) $categories = [$category_id];

            for( $i = 0 ; $i < count($categories) ; $i++ ){
                if( $res = $this->db->query("SELECT `category_id` FROM `".DB_PREFIX."category` WHERE `parent_id` = ". $categories[$i] )->rows )
                    foreach( $res as $v )
                        $categories[] = (int)$v['category_id'];
            }
        
            foreach( $this->db->query( " SELECT `product_id` FROM `".DB_PREFIX."product_to_category` WHERE `category_id` in (". implode( ',' , $categories ) .") " )->rows as $v )
                $products[] = $v['product_id'];
        }
            

        if( $find == 'attributes' && ( ( $categories && $products ) || !$categories ) ){
                
                if( $products )
                    $where_sql = ' `attribute_id` in ( SELECT `attribute_id` FROM `'.DB_PREFIX.'product_attribute` WHERE `product_id` in (' . implode( ',' , $products ) . ') ) AND ';
                    
                if( $id ){
                    $sql = 'SELECT DISTINCT `text` as `value` FROM `'.DB_PREFIX.'product_attribute` WHERE `language_id` = '.(int)$this->config->get('config_language_id').' AND `attribute_id` = \''.(int)$id.'\' AND '. ( $products ? ' `product_id` in (' . implode( ',' , $products ) . ') AND ' : '' ) .' `text` LIKE \'%'.$value.'%\' ORDER BY '. ( $name ? 'CHAR_LENGTH(`text`) DESC' : '`text` ASC' ) ;
                }else{
                    $sql = 'SELECT `attribute_id` as `id` , `name` FROM `'.DB_PREFIX.'attribute_description` WHERE `language_id` = '.(int)$this->config->get('config_language_id').' AND '.$where_sql.' `name` LIKE \'%'.$name.'%\' ORDER BY '. ( $name ? 'CHAR_LENGTH(`name`) DESC' : '`name` ASC' );
                }

        }elseif( $find == 'options' && ( ( $categories && $products ) || !$categories ) ){
            
                if( $id ){ 
                    $sql = " SELECT ovd.`name` as `value` FROM ( SELECT `option_id`, `option_value_id` FROM `".DB_PREFIX."product_option_value` WHERE `option_id` = ".$id." ". ( $products ? "AND  `product_id` in (" . implode( ',' , $products ) . ")" : "" ) ." ) as pov left join ( SELECT `option_id`, `name`, `option_value_id` FROM `".DB_PREFIX."option_value_description` WHERE `language_id` = ".(int)$this->config->get('config_language_id')." AND `option_id` = '".(int)$id."' AND `name` LIKE '%".$value."%' ) as ovd USING( option_value_id , option_id ) UNION ( SELECT `value` FROM `".DB_PREFIX."product_option` WHERE `option_id` = '".(int)$id."' ". ( $products ? "AND  `product_id` in (" . implode( ',' , $products ) . ")" : "" ) ." AND `value` LIKE '%".$value."%'  AND `value` <> '' ) ORDER BY ". ( $name ? 'CHAR_LENGTH(`value`) DESC' : '`value` ASC' ) ;
                }else{
                    if( $products )
                        $where_sql = ' `option_id` in ( SELECT `option_id` FROM `'.DB_PREFIX.'product_option` WHERE `product_id` in (' . implode( ',' , $products ) . ') ) AND ';
                    
                    $sql = 'SELECT `option_id` as `id` , `name` FROM `'.DB_PREFIX.'option_description` WHERE `language_id` = '.(int)$this->config->get('config_language_id').' AND '.$where_sql.' `name` LIKE \'%'.$name.'%\' ORDER BY '. ( $name ? 'CHAR_LENGTH(`name`) DESC' : '`name` ASC' ) ;        
                }

        }elseif( $find == 'categories' )
            $sql = 'SELECT DISTINCT `category_id` as `id` , `name` FROM `'.DB_PREFIX.'category_description` WHERE '. ( $category_id && $categories ? ' `category_id` in ('. implode(',', $categories) . ') AND `category_id` <> '.$category_id.' AND ' : '' ) .' `name` LIKE \'%'.$value.'%\' ORDER BY CHAR_LENGTH(`name`) DESC ';        

        if($sql)
            $json['finder'] = $this->db->query( $sql )->rows;

        return $json;
        
    }


}

?>