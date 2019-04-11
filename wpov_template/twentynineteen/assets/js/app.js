

var wpov_twentynineteen = function() {
    
    var table_sortable = function() {
        $.bootstrapSortable(true);
    }
    
    table_sortable();
    
    var copytoclipboard = function() {
        var data_id = 'copytoclipboard';
            console.log($('[data-'+data_id+']'));
        
        $('[data-'+data_id+']').each(function() {
            var $t = $(this),
                $input = $($t.data(data_id)),
                triggerSelect = 'wpov-copytoclipboard/input/select';
            
            $input.on('click', function() {
                $input.trigger(triggerSelect)
            }).on(triggerSelect, function() {
                $input.select()
            })
            ;
            
            $t.on('click', function() {
                $input.trigger(triggerSelect);
                document.execCommand("copy");
                alert(wpov_translate('The link was copied!'));
            })
        });
    }
    copytoclipboard();
    
    var tooltip = function() {
        $('[data-toggle="tooltip"]').tooltip();
    }
    tooltip();
}

$(function () {
    wpov_twentynineteen();
})