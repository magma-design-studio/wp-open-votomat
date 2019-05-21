

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
    
    
    var sticky_table_parts = function() {
        
        var $stickyCss = function($elem) {
            return $elem;
            
            $elem.css({
                position: 'sticky',
                top: 0
            });
            return $elem;
        };
        
        var $stickies = jQuery('.wpov-sticky'),
            resizeTrigger = 'wpov-sticky_table_parts-resize';
                
        $stickies.each(function() {
            var $sticky = jQuery(this),
                $stickyClone = $sticky.clone(true, true),
                $table = $sticky.closest('table'),
                $container = $table.parent(),
                $tableContainer = jQuery('<div></div>').append($table),
                $stickyTable = $table.clone().html('').addClass('sticky-top'),
                $stickyContainer = jQuery('<div></div>').addClass('sticky-top')

            this.sticky_table_parts = {};
            this.sticky_table_parts.$clone = $stickyClone;
            
            $container.append($tableContainer);
            $stickyTable.append($stickyCss($stickyClone));
            
            $table.before($stickyContainer.append($stickyTable));
            
            console.log($sticky.find('td,th'));
            
            //$sticky.css({ opacity : 0 });
                        
            $sticky.find('td,th').each(function(i) {
                var $td = jQuery(this);
                jQuery(window).on('resize ' + resizeTrigger, function() {
                    console.log();
                    $stickyClone.find('th:nth-child(' + (i+1) + '),td:nth-child(' + (i+1) + ')').css({ width : $td[0].offsetWidth })
                })                     
            })      
            
            jQuery(window).on('resize ' + resizeTrigger, function() {
                $stickyTable.css({ 
                    width : $table.width(),
                    marginBottom : 0,
                });
                                
                $container.css({ height: jQuery(window).height() })
                
                $table.css({
                    marginTop : ($stickyTable.height()*-1)
                })                
            })                
            
            
        });
        
        jQuery(window).trigger(resizeTrigger);
    }
    
    sticky_table_parts();
}

$(function () {
    wpov_twentynineteen();
})