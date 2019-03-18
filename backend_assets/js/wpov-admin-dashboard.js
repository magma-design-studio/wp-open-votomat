var wpov_admin_dashboard = function() {
    var content = {
        init: function() {
            console.log('wpov_admin_dashboard');
            var _ = this;
            jQuery('canvas[data-chart]').each(function() {
                var $t = jQuery(this),
                    fn = $t.data('chart');
                
                _.charts[fn]($t);
            })
        },
        charts: {
            opinion_poll : function($t) {
                var $canvas = $t,
                    $dataset = jQuery($canvas.data('set')),
                    dataset = jQuery.parseJSON($dataset.text()),
                    ctx = $canvas[0].getContext('2d');

                var myBarChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: dataset.labels,
                        datasets: [{
                            label: "Voters",
                            backgroundColor: 'rgb(255, 99, 132)',
                            borderColor: 'rgb(255, 99, 132)',
                            data: dataset.data,
                        }]
                    },                    
                    options: {}
                });            
            },
            opinion_poll_questions : function($t) {
                var $canvas = $t,
                    $dataset = $t.next('script'),
                    dataset = jQuery.parseJSON($dataset.text()),
                    ctx = $canvas[0].getContext('2d');                
                console.log(dataset);
                var myPieChart = new Chart(ctx,{
                    type : "doughnut",
                    "data": {
                        "labels":["Approve","Neutral","Disapprove"], 
                        "datasets": [{
                            "data":dataset,
                            "backgroundColor":["rgb(93, 164, 35)","rgb(255, 204, 1)","rgb(182, 1, 12)"]
                        }]
                    },
                    options : {
                        legend : {
                            display : false
                        }
                    }
                });                
                
            }
        }

    };

    
    
    
    content.init();
    return content;
};

wpov_admin.plugins.register('wpov_admin_dashboard', wpov_admin_dashboard);

