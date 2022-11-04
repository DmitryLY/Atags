// 2022 (C) Dmitry Y. Lepikhin

window.addEventListener("beforeunload", function(e){
    return;
})

window.addEventListener("load",function(){

    var templates_ = document.querySelectorAll('*[data-atags-template]');
    var templates = {};

    for( var i = 0; i < templates_.length ; i++ ){
        templates_[i].remove();
    }

    for( var i = 0; i < templates_.length ; i++ ){
        var id = templates_[i].getAttribute('data-atags-template');
        templates[ id ] = templates_[i][ templates_[i].tagName === 'TEMPLATE' ? 'innerHTML' : 'outerHTML' ].replaceAll('\\{','{').replaceAll('\\}','}').replaceAll('&amp;','&');
    }

    function atagsAjax(options){
   
        if(!options.url)return;

        var ajax = new XMLHttpRequest();
         
        ajax.open((options.type || 'GET'), options.url, ( (options.async!==undefined)? options.async : true )  );
  
        options.beforeSend && options.beforeSend();
                    
        ajax.onreadystatechange = function(){

            if(ajax.readyState != 4) return;

            if(ajax.status != 200){
                if(options.error)
                    options.error(ajax);
            }else{
                switch(options.dataType){
                    case 'html':{
                        out = ajax.responseText;
                        break;
                    }
                    case 'json':{
                        try{
                            out = ajax.responseJSON = JSON.parse(ajax.response)
                        }catch(e){
                            console.log('Error AJAX' + e + '  ' , ajax);
                            
                            if(options.error)
                                options.error(ajax);
                            return false;
                        }
                        break;
                    }

                }

                if(options.success)
                    if(options.success( out, ajax ) !== null && options.complete)
                        options.complete(ajax)
            }

        }

        var data = new FormData();

        for(var key in options.data)
            data.append(key, options.data[key])
    
        ajax.send( data ) ;

        return ajax;

    }

    Vue.createApp().
        component('atags-button-refresh', {
            template: templates.button_refresh,
            methods:{
                refresh_binding(){
                    var button = this.$refs.button;
                    atagsAjax({
                        url: decodeURIComponent(document.URL || window.location.href),
                        type: 'post',
                        data: { refresh_binding: true },
                        dataType: 'json',
                        beforeSend:  function() {
                            if( button.className.search(/\batags_turning\b/) === -1 )
                                button.className += " atags_turning";
                        },
                        complete: function() {
                            button.className = button.className.replace(/(^|\s+)atags_turning\b/, '');
                        },
                        success: function(json) {
                            
                        }
                    });

                }
            }
        }).
        mount('#refresh_binding_cont')
    


    var languages, stores, list_tags, settings, language_index;

    atagsAjax({
        url: decodeURIComponent(document.URL || window.location.href),
        type: 'post',
        data: { init: true },
        dataType: 'json',
        beforeSend: function() {
            
        },
        complete: function() {
            
        },
        success: function(json) {
            if( !json ) return;

            languages = json.languages;
            i = 0;
            for( var key in languages ){
                if( languages[key]['language_id'] == json.language_current ){
                    language_index = i; break;
                }
                i++;
            }
                

            stores = json.stores;
            settings = json.settings;
            language_ = json.language_;
            
            init_atags();

        }
    });

    function init_atags(){

        function save_dataSettings(){
            atags.settings.saved_ = {};
            atags.settings.saved_.status = atags.settings.status;
        }

        function compare_settings(){
            var changed = false;
            if( atags.settings.saved_.status !== atags.settings.status )
                changed = true;
            
            atags.settings.changed = changed;
        }

        function save_data(){
            var current = atags.current;
            if( !current.tag_id ) return;
            
            current.saved_ = {};
            current.saved_.status = current.status;
            current.saved_.langs = current.langs !== undefined && JSON.parse(JSON.stringify(current.langs));
            current.saved_.stores_id = current.stores_id !== undefined && JSON.parse(JSON.stringify(current.stores_id));
            current.saved_.category = current.category !== undefined && JSON.parse(JSON.stringify(current.category));
            current.saved_.bound_values = current.bound_values !== undefined && JSON.parse(JSON.stringify(current.bound_values));
            
            current.saved_.manually_products = [];
            for( var i = 0;  i < current.bound_products.length ; i++ ){
                if( current.bound_products[i].manually === '1' )
                    current.saved_.manually_products.push( JSON.parse(JSON.stringify( current.bound_products[i] )) );
            }
            
        }

        function compare_data(field){ 
                var current = atags.current;
                if( !current.tag_id ) return;

                var changed = false;
            if( !changed && current.status !== current.saved_.status ){
                changed = true;
            }

            if( !changed )
                main_langs:for( var i = 0; i < current.langs.length; i++ ){
                    for( var key in current.langs[i] ){
                        if( !current.langs[i].hasOwnProperty(key) ) continue;
                        if( key === 'seo_keyword' )
                            for( var j = 0; j < current.langs[i][key].length; j++ ){
                                if( current.langs[i][key][j].keyword !== current.saved_.langs[i][key][j].keyword ){
                                    changed = true;
                                    break main_langs;
                                }
                            }
                        else 
                            if( current.langs[i][key] !== current.saved_.langs[i][key] ){
                                changed = true;
                                break main_langs;
                            }
                        
                    }
                }

       
            if( !changed ){

                if( current.stores_id.length !== current.saved_.stores_id.length )
                    changed = true;
                else
                    for( var i = 0; i < current.stores_id.length; i++ ){
                        if( current.saved_.stores_id.indexOf( current.stores_id[i] ) === -1 ){
                            changed = true;
                            break;
                        }
                    }
            }
            
            if( !changed ){
                var id = current.category && current.category.id || 0;
                var id_s = current.saved_.category && current.saved_.category.id || 0;
                if( id !== id_s )
                    changed = true;
            }
                
            if( !changed ){
                main_c:
                for( var key in current.bound_values ){
                    if( !current.bound_values.hasOwnProperty(key) ) continue;
                    
                    if( current.bound_values[key].length !== current.saved_.bound_values[key].length ) {
                        changed = true;
                        break;
                    }
                    sub_c:
                    for( var i = 0; i < current.bound_values[key].length; i++ ){
                        var id = current.bound_values[key][i].id;
                        var found_id = false;
                        sub_c1:
                        for( var j = 0; j < current.saved_.bound_values[key].length; j++ ){
                            var id_s = current.saved_.bound_values[key][j].id;

                            if( id_s === id ){
                                if( current.bound_values[key][i].hasOwnProperty('values') ){
                                    var values = current.bound_values[key][i].values;
                                    var values_s = current.saved_.bound_values[key][j].values;

                                    if( values.length !== values_s.length ){
                                        break;
                                    }else
                                        for( var k = 0; k < values.length; k++ ){
                                            var found_v = false;
                                            for( var p = 0; p < values_s.length; p++ ){
                                                if( values[k].value === values_s[p].value ){
                                                    found_v = true;
                                                    break;
                                                }
                                            }
                                                if( !found_v )
                                                    break sub_c1;
                                        }
                                }
                                found_id = true;
                            }
                        }
                        
                        if( !found_id ){
                            changed = true;
                            break main_c;
                        }
                    }

                }
            }

            if( !changed ){

                var length_m = 0;
                
                    sub_c:
                    for( var i = 0; i < current.bound_products.length; i++ ){
                        if( current.bound_products[i].manually !== '1' ) continue;
                        length_m++;
                        var id = current.bound_products[i].id;

                        for( var j = 0; j < current.saved_.manually_products.length; j++ ){
                            var id_s = current.saved_.manually_products.id;
                            if( id === id_s )
                                continue sub_c;
                        }

                        changed = true;
                        break;
                    }

                    if( length_m !== current.saved_.manually_products.length )
                        changed = true;

            }

            current.changed = changed;

        }

        var app = Vue.createApp({
            data(){
                var data = {
                    tabs:[
                        {
                            title: language_.tags,
                            list: [/*[
                                {   
                                    id: undefined,
                                    name: 'Название тэга',
                                    store: 'Магазин'
                                },
                            ]*/],
                            list_limit: [],
                            loading_filter: false,
                            main: true,
                            query_string: '',
                            query_store_id: 'all',
                            page: 1,
                            limit: 16,
                        }
                    ]
                };

                data.settings = {
                    setting: true,
                    loading: false,
                    changed: false,
                    title: language_.settings,
                    status: 0,
                    saved_: {}
                };

                for( var field in settings )
                    if( settings.hasOwnProperty(field) )
                        data.settings.saved_[field] = data.settings[field] = settings[field];

                data.current = data.settings;
                data.stores = stores;
                
                

                return data;
            },
            provide() {
                return { 
                    tabs: this.tabs, 
                    createTab: this.createTab, 
                    deleteTag: this.deleteTag, 
                    closeTab: this.closeTab, 
                    saveTab: this.saveTab, 
                    settings: this.settings, 
                    stores: this.stores, 
                    languages: languages, 
                    getTags: this.getTags, 
                    getList: this.getList, 
                    getPages: this.getPages, 
                    saveSettings: this.saveSettings, 
                    setCurrent: this.setCurrent,
                    language_index: language_index
                }
            },
            beforeMount(){
                this.getTags();
            },
            mounted(){
                $('[data-toggle=\'tooltip\']').tooltip();
            },
            methods:{
                saveSettings(){

                    if( this.settings.loading ) 
                        return;

                    var data = {status: undefined}, settings = this.settings;

                    for( var field in data )
                        if( !settings.hasOwnProperty(field) ) continue;
                        else    
                            data['module_atags_'+field] = settings[field];
                    
                    atagsAjax({
                        url: decodeURIComponent(document.URL || window.location.href),
                        type: 'post',
                        data: { save_settings: JSON.stringify(data) },
                        //async:false,
                        dataType: 'json',
                        beforeSend: function() {
                            settings.loading = true;
                        },
                        complete: function() {
                            settings.loading = false;
                            save_dataSettings();
                        },
                        success: function(json) {
                            atags.settings.changed = false;
                        }
                    });

                },getList(){
                    var list = [];
                    
                    if( this.tabs[0].page * this.tabs[0].limit - this.tabs[0].limit >= this.tabs[0].list.length ) 
                        this.tabs[0].page--;

                    for( var i = this.tabs[0].page * this.tabs[0].limit - this.tabs[0].limit;  i < this.tabs[0].page * this.tabs[0].limit; i++ )
                        if( this.tabs[0].list[i] )
                            list.push( this.tabs[0].list[i] );

                    this.tabs[0].list_limit = list;
                        
                },getPages(){
                    var pages = [];
                    for(var i = 0; i < this.tabs[0].list.length / this.tabs[0].limit; i++)
                        pages.push(i+1)
                    
                    return pages;
                },
                getTags(){
                    if( this.tabs[0].loading_filter )
                        return;

                    var tab = this.tabs[0], get_list = this.getList,
                    request = { query: tab.query_string, store_id: tab.query_store_id, page: tab.page || ( tab.page = 1 ) , limit: tab.limit || ( tab.limit = 10 ) };

                    atagsAjax({
                        url: decodeURIComponent(document.URL || window.location.href),
                        type: 'post',
                        data: {get_tags: true , request: JSON.stringify( request ) },
                        //async:false,
                        dataType: 'json',
                        beforeSend: function() {
                            tab.loading_filter = true;
                        },
                        complete: function() {
                            tab.loading_filter = false;
                        },
                        success: function(json) {
                            tab.list = json.list;
                            tab.found_rows = json.found_rows;
                            get_list();
                        }
                    });

                },
                setCurrent(tab){
                    if( tab !== this.settings && this.tabs.indexOf(tab) === -1 ) return;
                        this.current = tab;
                },deleteTag(item){

                    if( !item.tag_id || !confirm(language_.confirm_delete) ) return;

                        var deleted;

                        atagsAjax({
                            url: decodeURIComponent(document.URL || window.location.href),
                            type: 'post',
                            data: {delete_tag: true, tag_id: item.tag_id },
                            async: false,
                            dataType: 'json',
                            beforeSend: function() {
                                
                            },
                            complete: function() {
                                
                            },
                            success: function(json) {

                                deleted = json.deleted;

                            }
                        });

                        if( !deleted ) return alert(language_.error_delete);

                        atags.tabs[0].list.splice( atags.tabs[0].list.indexOf(item) , 1 );

                        for( var i = 0; i < atags.tabs.length; i++ ){
                            if( atags.tabs[i].tag_id != item.tag_id ) continue;
                                
                            atags.tabs.splice( i , 1 ); i--; 
                        }

                        this.getList();

                },
                closeTab(tab, event){
                    event.stopPropagation();

                    if( ( !tab.tag_id || tab.changed ) && !confirm(language_.confirm_close_tag) ) return ;

                    var index;
                    if( tab.main || tab.settings || ( index = this.tabs.indexOf(tab) ) === -1 ) return;

                    if( atags.current === tab )
                        this.setCurrent( this.tabs[index - 1] || this.tabs[0] );

                    this.tabs.splice( index , 1 );
                },
                createTab( tag_id , copy ){

                    var languages_data = [], got_tab;

                    if( tag_id ){

                        if( !copy )
                            for( var i = 0; i < atags.tabs.length; i++ ){
                                if( atags.tabs[i].tag_id != tag_id ) continue;
                                    
                                this.setCurrent(atags.tabs[i]);
                                return;
                            }

                        atagsAjax({
                            url: decodeURIComponent(document.URL || window.location.href),
                            type: 'post',
                            data: {get_tab: true, tag_id: tag_id },
                            async:false,
                            dataType: 'json',
                            beforeSend: function() {
                                
                            },
                            complete: function() {
                                
                            },
                            success: function(json) {

                                if( copy ){
                                    json.tag_id = 0;
                                    json.changed = true
                                }else
                                    json.changed = false

                                got_tab = json;

                            }
                        });

                        if( !copy && !got_tab.tag_id )
                            return;
                        
                    }else{

                        for( var item in languages ){
                            if( !languages.hasOwnProperty(item) ) continue;

                            var lang = {   
                                language_id: languages[item].language_id,
                                name: language_.name_tag_,
                                short_name: '',
                                full_name: '',
                                meta_title: '',
                                header_h1: '',
                                meta_description: '',
                                description: '',
                                seo_keyword: {},
                                code: item,
                                name_lang: languages[item].name
                            };

                            lang.seo_keyword = [];

                            for(var i = 0; i < stores.length; i++)
                                lang.seo_keyword.push( { store_id: stores[i].store_id, name: stores[i].name, keyword: '' } );
                                
                                languages_data.push(lang);
                                
                        }

                    }

                        var tab = {
                            tag_id: 0,
                            status: 0,
                            category: undefined,
                            stores_id: [],
                            errors: { langs: {} },
                            langs: languages_data,
                            current_lang: languages_data[0] || got_tab.langs[0],
                            loadings: {},
                            inputs:{
                                categories:{
                                    categories_value: '',
                                    categories_finder: [],
                                    focused_categories: false,
                                    temp: undefined,
                                },
                                category:{
                                    category_value: '',
                                    category_finder: [],
                                    focused_category: false,
                                },
                                attributes:{
                                    name: '',
                                    name_finder: [],
                                    focused_name: false,
                                    temp: undefined,
                                    value: '',
                                    focused_values: false,
                                    values_finder: [],
                                },
                                options:{
                                    name: '',
                                    name_finder: [],
                                    focused_name: false,
                                    temp: undefined,
                                    value: '',
                                    focused_values: false,
                                    values_finder: [],
                                },
                                products:{
                                    product_id: '',
                                    name: '',
                                    model: '',
                                    category_value: '',
                                    category: false,
                                    focused_category: false,
                                    category_finder: []
                                }
                            },
                            bound_values:{
                                categories:[/*{id: undefined, name: undefined}*/],
                                attributes:[/*{id: undefined, name: undefined, values: []}*/],
                                options:[/*{id: undefined, name: undefined, values: []}*/],
                            },
                            bound_products:[
                                /*{ id: undefined , name: undefined , model: undefined }*/
                            ],
                            found_products:[
                                /*{ id: undefined , name: undefined , model: undefined }*/
                            ],
                            saved_:{
                                status: undefined,
                                langs: undefined,
                                stores: undefined,
                                category: undefined,
                                bound_values: undefined,
                                manually_products: undefined
                            }
                        }



                    

                    if( got_tab )
                        for( var item in got_tab){
                            if( !got_tab.hasOwnProperty(item) ) continue;
                            tab[item] = got_tab[item];
                        }

                    this.tabs.push(tab);
                    this.setCurrent(tab);
                    save_data();

                },
                saveTab(){

                    var data = {}, errors = undefined, lang_arr, current = this.current;

                    if( current.loadings && current.loadings.send )
                        return;

                    current.errors = { langs: {} };

                    for( var lang in current.langs ){
                        lang_arr = current.langs[lang];
                        for( var lang_ in lang_arr ){
                            if( !lang_arr.hasOwnProperty(lang_) ) continue;
                            if( ( lang_ == 'name' || lang_ == 'short_name' || lang_ == 'full_name' || lang_ == 'meta_title' ) && lang_arr[lang_].trim() == '' ){
                                if( !current.errors.langs[lang_arr.language_id] ) 
                                    current.errors.langs[lang_arr.language_id] = {};
                                current.errors.langs[lang_arr.language_id][lang_] = language_.error_empty;
                                errors = true;
                            }
                            else if( lang_ = 'seo_keyword' )
                                for( var store_seo in lang_arr[lang_] ){
                                    if( !lang_arr[lang_].hasOwnProperty(store_seo) ) continue; 
                                    if( lang_arr[lang_][store_seo].keyword.search(/[^\w\d-\_\+]+/) !== -1 ){
                                        if( !current.errors.langs[lang_arr.language_id] ) 
                                            current.errors.langs[lang_arr.language_id] = {};
                                        if( !current.errors.langs[lang_arr.language_id]['seo_keyword'] ) 
                                            current.errors.langs[lang_arr.language_id]['seo_keyword'] = {};
                                        current.errors.langs[lang_arr.language_id]['seo_keyword'][store_seo] = language_.error_seo_keyword;
                                        errors = true; 
                                    }
                                }
                        }
                    }
                    
                    if( errors ) return window.scrollTo(0,0);

                    data.tag_id = current.tag_id;
                    data.status = current.status;
                    data.category_id = current.category ? current.category.id : 0;
                    data.stores_id = current.stores_id;
                    data.langs = current.langs;
                    data.bound_values = current.bound_values;

                    data.bound_products = [];

                    for(var i = 0; i < current.bound_products.length; i++)
                        if( current.bound_products[i].manually === '1' )
                            data.bound_products.push( current.bound_products[i].product_id );
                    
                    atagsAjax({
                        url: decodeURIComponent(document.URL || window.location.href),
                        type: 'post',
                        data: {save_tab: true, data: JSON.stringify( data ) },
                        dataType: 'json',
                        beforeSend: function() {
                            current.loadings.send = true;
                        },
                        complete: function() {
                            atags.getTags();
                        },
                        success: function(json) {
                            current.loadings.send = false;
                            if( json.errors && Object.keys(json.errors).length ){
                                current.errors = json.errors;
                                window.scrollTo(0,0);
                                return;
                            }

                            if( !json.tag_id && !data.tag_id ) return alert(language_.error_id);

                            current.bound_products = json.bound_products || [];
                            current.bound_values = json.bound_values;

                            if( !data.tag_id ) current.tag_id = json.tag_id;

                                current.changed = false;
                                current.errors = {};
                                save_data();

                        }
                    });

                    
                }


            }
        });

        app.component('atags-settings', {
            inject:['saveSettings'],
            props: ['current'],
            template: templates.settings,
            beforeUpdate(){
                compare_settings();
            }
        });

        app.component('atags-tabs', {
            inject: ['tabs','createTab','settings'],
            props: ['current'],
            template: templates.tabs
        });

        app.component('atags-tab', {
            inject: ['setCurrent', 'closeTab', 'language_index'],
            props: ['tab','current'],
            template: templates.tab
        });

        app.component('atags-content', {
            inject: ['tabs','createTab','getList'],
            props: ['current'],
            template: templates.content
        });

        app.component('atags-stores',{
            props: ['item','current'],
            template: templates.stores,
            methods:{
                selectStores(item){
                    var current = this.$props.current;

                    var checked = this.$refs.input.checked,
                    store_id = String(item.store_id),
                    index = current.stores_id.indexOf( store_id );
                    
                    if( checked ){
                        if( index === -1 )
                            current.stores_id.push( store_id );
                    }else if( index !== -1 ){
                        current.stores_id.splice( index , 1 );
                    }

                    compare_data('stores_id');
                },
                checkStore(store_id){
                    var current = this.$props.current;

                    var check = false;
                    for( var i = 0; i < current.stores_id.length; i++ ){
                        if( current.stores_id[i] == store_id ){
                            check = true;
                            break;
                        }
                    }

                    return check;
                }
            }
        })

        app.component('atags-list', {
            inject: ['getTags','getPages'],
            props: ['current'],
            template: templates.list,
            mounted(){
                $('[data-toggle=\'tooltip\']').tooltip()
            }
        });

        app.component('atags-list-item', {
            props: ['item'],
            inject: ['createTab','deleteTag'],
            template: templates.list_item
        });

        app.component('atags-page', {
            props: ['current'],
            inject: ['saveTab','stores'],
            template: templates.tag_page,
            beforeUpdate(){
                compare_data('bound_values');
            }
        });

        app.component('atags-languages-tabs', {
            inject: ['languages'],
            props: ['current','lang'],
            template: templates.languages_tabs,
            methods:{
                setLang(current,lang){
                    current.current_lang = lang;
                }
            }
        });

        app.component('atags-stores-seo-keywords', {
            props: ['store','current','errors'],
            template: templates.stores_seo_keywords,
            methods:{
                check_keyword(e){
                    this.$props.store.keyword = this.$props.store.keyword.replace(/[^\w\d-\_\+]+/,'')
                }
            },
            beforeUpdate(){
                compare_data('bound_values');
            }
        });

        app.component('atags-pagination', {
            inject: ['getList'],
            props: ['page','active_page'],
            template: templates.pagination,
            methods:{
                setPage(page){
                    atags.current.page = page;
                    this.getList();
                }
            }
        });

        app.component('atags-language-desc', {
            props: ['current','errors'],
            template: templates.language_desc
        });

        app.component('atags-input-desc', {
            props: ['current','errors','field'],
            template: templates.input_desc,
            beforeUpdate(){
                compare_data('langs');
            }
        });

        app.component('atags-input-finder', {
            props: [ 'modelValue' , 'list' , 'find' , 'container' , 'what' ],
            emits: [ 'update:modelValue' ],
            template: templates.input_finder,
            methods:{
                find_bd(container,what,modelValue,find){

                    if( container['loading_'+what] )
                        return;
                    
                    var data = { finder: find, what: what }, arr, current = atags.current;
                    
                    if( container !== current.inputs.category && current.category && current.category.id )
                        data.category_id = current.category.id;
                    
                    if( (arr = current.bound_values.categories).length ){
                        data.categories = [];
                        for( var i = 0; i < arr.length; i++ )
                            data.categories.push(arr[i].id)
                        data.categories = JSON.stringify( data.categories );
                    }

                    if( what === 'name' )
                        data.name = modelValue;
                    else if( what === 'value' && container.temp )
                        ( data.value = modelValue ) , ( data.id = container.temp.id );
                    else if( find === 'categories' ){
                        ( data.value = modelValue );
                    }else
                        return;

                    container['focused_'+( (what === 'value' && 'values') || ( what === 'name' && 'name' ) || what )] = true;
                    
                    atagsAjax({
                        url: decodeURIComponent(document.URL || window.location.href),
                        type: 'post',
                        data: data,
                        dataType: 'json',
                        beforeSend: function() {
                            container['loading_'+what] = true;
                        },
                        complete: function() {
                            container['loading_'+what] = false;
                        },
                        success: function(json) {
                            container[ ( (what === 'value' && 'values') || (what === 'name' && 'name') || what ) + '_finder' ] = what === 'value' ? Object.values( json.finder ) : json.finder;
                        }
                    });

                }
            },
            mounted(){
                var comp = this;
                this.outside = function(e){ 
                    var elem = e.target;
                    if( atags.current.inputs )
                        while( elem ){
                            if( elem === comp.$el )
                                return;
                            
                            elem = elem.parentNode;
                        }
                    if( elem === null )
                        comp.$props.container['focused_'+( (comp.$props.what === 'value' && 'values') || (comp.$props.what === 'name' && 'name') || comp.$props.what )] = false;
                        
                }
                
                window.addEventListener('click', this.outside );
            },
            unmounted(){
                window.removeEventListener('click', this.outside );
            }
        });
       

        app.component('atags-input-found-item', {
            props: [ 'item' , 'container' , 'what' ],
            template: templates.input_found_item,
            methods:{
                setTemp( item, container, what ){
                    container[ 'focused_'+( (what === 'value' && 'values') || ( what === 'name' && 'name' ) || what ) ] = false;
                    if( what === 'name' ){
                        if( item.id && item.name  )
                            container.temp = { id: item.id , name: item.name };

                        container.name = item.name;
                        container.value = '';
                        container.values_finder = [];

                    }else if( what === 'value'  ){
                        if( container.temp && item.value.length ){
                            container.temp.value = item.value;
                            container.value = item.value;
                        }
                    }else if( what ){
                        if( container.hasOwnProperty('temp') )
                            container.temp = item;
                        else {
                            if( container.hasOwnProperty('model') )
                                container[what] = item;
                            else{
                                atags.current[what] = item;
                            }
                        }

                        container[what+'_value'] = item.name;
                    }

                }
            }
        });


        app.component('atags-bound-values', {
            props: [ 'item' , 'container', 'items', 'to' ],
            template: templates.bound_values ,
            methods:{
                delete( item, container ){
                    container.splice( container.indexOf(item) , 1 );
                },edit( item , to ){
                    if( atags.current.inputs[to].temp && item.id == atags.current.inputs[to].temp.id ) return;
                    atags.current.inputs[to].temp = item;
                    atags.current.inputs[to].name = item.name;
                    atags.current.inputs[to].value = '';
                    atags.current.inputs[to].values_finder = [];
                }
            },
            beforeUpdate(){
                compare_data('category');
            }
        });

        app.component('atags-bound-values-item', {
            props: [ 'item', 'items' ],
            template: templates.bound_values_item,
            methods:{
                delete( item, items ){
                    if(items){
                        var index = items.indexOf( item );
                        if( index === -1 ) return;

                        items.splice( index , 1 );
                    }else if( item )
                        atags.current.category = undefined;

                }
            },
            beforeUpdate(){
                compare_data('category');
                compare_data('bound_values');
            }
        });

        app.component('atags-add-bound-value', {
            props: [ 'container' , 'to' ],
            template: templates.add_bound_value,
            methods:{
                add( container, to ){
                    var bound = container.bound_values[to],
                    value, category_value,
                    temp = container.inputs[to].temp;

                    if( container.inputs[to].hasOwnProperty('value') && ( value = container.inputs[to].value.trim() ) && value ){
                        value = { value: value}
                        value.strict = value.value != temp.value ? false : true ;
                    }
                    

                    if( !temp ) return;
                   

                    if( bound.length )
                        for(var i = 0; i < bound.length ; i++ ){
                            if( bound[i].id !== temp.id ) continue;
                            
                            if( value ){
                                for(var j = 0; j < bound[i].values.length; j++)
                                    if( bound[i].values[j].value == value.value )
                                        var available = true;
                                
                                if(!available )
                                    bound[i].values.push(value)
                            }
                            return;
                        }

                        temp = { id: temp.id, name: temp.name };

                        if( to !== 'categories' ){
                            temp.values = [];
                            if( value ) temp.values.push( value );
                        }

                        bound.push( temp );


                }
            },
            beforeUpdate(){
                compare_data('bound_values');
            }
        });

        app.component('atags-filter-products', {
            props: ['current'],
            template: templates.filter_products,
            methods:{
                filter_products( current ){

                    if( current.loadings.filter_products )
                        return;

                    var bound_products = [];

                    for(var i = 0; i < current.bound_products.length; i++)
                            bound_products.push( current.bound_products[i].product_id );

                    var data = {filter_products: JSON.stringify( current.inputs.products ), bound_products: JSON.stringify(bound_products) };

                    if( current.category )
                        data.category_id = current.category.id;

                    atagsAjax({
                        url: decodeURIComponent(document.URL || window.location.href),
                        type: 'post',
                        data: data,
                        dataType: 'json',
                        beforeSend: function() {
                            current.loadings.filter_products = true;
                        },
                        complete: function() {
                            current.loadings.filter_products = false;
                        },
                        success: function(json) {
                            current.found_products = json;
                        }
                    });

                }
            }
        });

        app.component('atags-products-found', {
            props: ['list'],
            template: templates.products_found,
        });

        app.component('atags-products-list-found', {
            props: ['list'],
            template: templates.products_list_found,
            methods:{
                add( item , list ){
                    item.manually = '1';
                    atags.current.bound_products.unshift( item );
                    list.splice( list.indexOf( item ) , 1  );
                }
            },
            beforeMount(){
                compare_data('bound_products');
            }
        });

        app.component('atags-products-bound', {
            props: ['list'],
            template: templates.products_bound,
        });

        app.component('atags-products-list-bound', {
            props: ['list'],
            template: templates.products_list_bound,
            methods:{
                delete( item , list ){
                    list.splice( list.indexOf( item ) , 1  );
                }
            },
            beforeUpdate(){
                compare_data('bound_products');
            }
        });

        app.component('atags-button', {
            inject:['saveTab','saveSettings'],
            props: ['current'],
            template: templates.button
        });
        

        var atags = app.mount('#atags_container');

    }

});

