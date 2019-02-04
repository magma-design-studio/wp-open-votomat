

var wpov_admin = new (function() {
    var content = {
        init: function() {
            console.log('wpov_admin');
        },
        plugins: {
            register: function(key, fn) {
                this._[key] = fn()
            },
            _ : {

            }
        }
    }   
    
    content.init();
    return content;
})();

