<?php
// (C) 2022 Dmitry Y. Lepikhin 

class ControllerExtensionModuleAtags extends Controller{

    public function index(){

        $this->load->model('extension/module/atags');

        $this->model_extension_module_atags->createTables();

        if( $this->request->server['REQUEST_METHOD'] == 'POST' ){

            $json = [];
            
            if( isset( $this->request->post['get_tab'] ) && $this->request->post['get_tab'] && isset($this->request->post['tag_id']) && $this->request->post['tag_id'] ){

                $json = $this->model_extension_module_atags->get_tab();
               
            }elseif( isset( $this->request->post['delete_tag'] ) && $this->request->post['delete_tag'] && isset($this->request->post['tag_id']) && $this->request->post['tag_id'] ){

                if( $this->validate() )
                    $this->model_extension_module_atags->delete_tag();

                $json = ['deleted' => true];

            }elseif( isset( $this->request->post['refresh_binding'] ) && $this->request->post['refresh_binding'] && $this->validate() ){

                $this->model_extension_module_atags->refresh_binding();

            }elseif( isset( $this->request->post['save_tab'] ) && $this->request->post['save_tab'] && $this->validate() ){

                $json = $this->model_extension_module_atags->save_tab();
                
            }elseif( isset( $this->request->post['get_tags'] ) ){

                $json = $this->model_extension_module_atags->get_tags();
                            
            }elseif( isset( $this->request->post['init'] ) ){

                $json = $this->model_extension_module_atags->init();

            }elseif( isset( $this->request->post['save_settings'] ) ){

                $json = $this->model_extension_module_atags->save_settings();

            }elseif( isset( $this->request->post['filter_products'] ) ){

                $json = $this->model_extension_module_atags->filter_products();

            } elseif ( isset( $this->request->post['finder'] ) ) {

                $json = $this->model_extension_module_atags->finder();

            }

            
            
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode( $json )); 
            return;

        }

        $data = [];


        $data['heading_title'] = 'ATags';

		$this->document->addStyle('view/stylesheet/atags/atags.css?c='. filemtime('view/stylesheet/atags/atags.css') );
		$this->document->addScript('view/javascript/atags/vue.global.prod.js?c='. filemtime('view/javascript/atags/vue.global.prod.js') );
		$this->document->addScript('view/javascript/atags/atags.js?c='. filemtime('view/javascript/atags/atags.js') );


		$this->document->setTitle($data['heading_title']);

		$data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
					
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $data['cancel'] = $this->url->link('marketplace/extension', ['user_token' => $this->session->data['user_token'], 'type'=>'module'], true)
		);

        $data['breadcrumbs'][] = array(
			'text' => $data['heading_title'],
			'href' => $this->url->link('extension/module/atags', 'user_token='. $this->session->data['user_token'], true)
		);


        $this->response->setOutput($this->load->view('extension/module/atags', $data));


    }



    protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/atags')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

}

?>