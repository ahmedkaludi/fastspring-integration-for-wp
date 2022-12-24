jQuery( document ).ready( function ( $ ) {

    var current_payment_status = $("[name=edd-payment-status]").val();
    
    $("[name=edd-payment-status]").change(function(e){

        if($(this).val() == 'refunded'){

            var r = confirm("You have selected REFUNDED. Are you sure with your selection??");

            if (r == false) {
                $("[name=edd-payment-status]").val(current_payment_status);
            } 

        }

    });

    $("#edd-edit-order-form").submit(function(){
        
        var cps = $("[name=edd-payment-status]").val();

        if( cps == 'refunded' ){

            var r = confirm("Are you sure?. Do want to proceed with refund??");

            if (r == false) {
                return false;
            } 

        }

    });

});