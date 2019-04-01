

var wpov_admin = new (function() {
    var content = {
        init: function() {
            this.actions.init();
        },
        plugins: {
            register: function(key, fn) {
                this._[key] = fn()
            },
            _ : {

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

