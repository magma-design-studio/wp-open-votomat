

var wpov_admin = new (function() {
    var content = {
        init: function() {
            this.actions.init();
            this.overrides_notes.init();
        },
        plugins: {
            register: function(key, fn) {
                this._[key] = fn()
            },
            _ : {

            }
        },
        overrides_notes: {
            init: function() {
                var $b = jQuery('body');
                try {
                    var wpov_type = wpov_settings.admin_settings.wpov_type
                } catch(e) {
                    var wpov_type = false
                }

                console.log(wpov_type);

                var hideSettings = {
                    pointerEvents: 'none',
                    opacity: .5
                };
                
                if(wpov_type == 'standalone') {
                    if($b.hasClass('options-permalink-php')) {
                        jQuery('#wpbody-content .form-table').css(hideSettings)
                    } else if($b.hasClass('options-reading-php')) {
                        jQuery('#front-static-pages').css(hideSettings)
                    }
                    
                }
            }
        },        
        actions: {
            init: function() {
                var label = 'wpov-js-click-action';
                jQuery(document).on('click', '[data-'+ label +']', function(e) {
                    e.preventDefault();
                    var $t = jQuery(this),
                        data = $t.data(label);
                    
                    wpov_admin.actions.actions[data]($t);
                });
            },
            actions: {
                reset_user_votings: function($t) {
                    var confirm = window.confirm('Do you really want to reset the user data?');
                    
                    if(confirm == true) {
                        jQuery.post(ajaxurl, {
                            action : 'wpov_reset_user_votings',
                            post_id : $t.data('post_id')
                        }, function(data) {
                            console.log(data);
                            setTimeout(function() {
                                window.alert(data);
                            }, 200);
                        });                        
                    }
                }
            }
        }
    }   
    
    content.init();
    return content;
})();

