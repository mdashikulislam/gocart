<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<div id="card-element"></div>
<input type="hidden" name="payment_method" value="Credit Card">
<div id="stripe-loading" style="display:none; text-align:center;">
	<img alt="loading" src="<?php echo theme_img('ajax-loader.gif');?>">
</div>
    <script src="https://js.stripe.com/v3/"></script>
	<script type="text/javascript">
	$(document).ready(function(){
		alert($('#form-stripe_payments>input[type=submit]').attr('type'));

	});
    var publishableKey;
    <?php if($stripe['mode'] == 'test'):?>
    publishableKey = '<?php echo $stripe['test_publishable_key'];?>';
    <?php else: ?>
    publishableKey = '<?php echo $stripe['live_publishable_key'];?>';
    <?php endif;?>
    var stripe = Stripe(publishableKey);
    var elements = stripe.elements();
    var cardElement = elements.create('card');
    cardElement.mount('#card-element');
    var form = document.getElementById('form-stripe_payments');

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        fetch('<?=base_url('stripe-payment-intent?amount='.$this->go_cart->total())?>', {
            method: 'GET'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (session) {
                return stripe.confirmCardPayment(session.client_secret, {
                    payment_method: {
                        card: cardElement,
                    },
                });
            })
            .then(function (result) {
                // Handle the result
                if (result.error) {
                    alert(result.error.message);
                } else {

                    $("#form-stripe_payments").html("<input id='stripeToken' type='hidden' name='stripeToken' value='" + result.paymentIntent.id + "'>");
                    $('#form-stripe_payments').submit();
                }
            })
            .catch(function (error) {
                console.error(error);
            });




        // stripe.confirmCardPayment(
        //     'pi_3OIXUaADOb5VKhoH1jTBXtQA_secret_68YXJ8SgulAcFaetrKc4t9n9M',
        //     {
        //         payment_method: {
        //             card: cardElement,
        //         }
        //     }
        // ).then(function(result) {
        //     if (result.error) {
        //         // Handle errors here
        //         console.error(result.error);
        //     } else {
        //         console.log(result.paymentIntent);
        //     }
        // });



        // // Get the amount and card details from the user input
        // var amount = $('#amount-input').val();
        //
        // stripe.createPaymentMethod({
        //     type: 'card',
        //     card: cardElement,
        // }).then(function(result) {
        //     if (result.error) {
        //         // Handle errors here
        //         console.error(result.error);
        //     } else {
        //         // Payment method created successfully, handle the result here
        //         var paymentMethodId = result.paymentMethod.id;
        //
        //         // Perform client-side logic with payment method ID and amount
        //         console.log('Payment method ID:', paymentMethodId);
        //         console.log('Amount:', amount);
        //
        //         // Note: For security reasons, important transaction-related logic
        //         // should be performed on the server side.
        //
        //         // Example: Send payment method ID and amount to an analytics service
        //         sendToAnalytics(paymentMethodId, amount);
        //     }
        // });
    });

    function sendToAnalytics(paymentMethodId, amount) {
        console.log('Sending data to analytics:', { paymentMethodId, amount });
        // Implement your analytics integration logic here
    }

		//$.getScript('https://js.stripe.com/v1/', function(){
		//
		//	stripe_payments = function(){
		//
		//		// ensure that the error field is cleared out.
		//		$('#stripe_error').html('').hide();
        //
		//		$('#form-stripe_payments>input[type=button]').hide();
		//		$('#stripe-loading').show();
		//		$('#stripe-details-form').hide();
		//
		//		//set publishable key
		//		<?php //if($stripe['mode'] == 'test'):?>
		//		Stripe.setPublishableKey('<?php //echo $stripe['test_publishable_key'];?>//');
		//		<?php //else: ?>
		//		Stripe.setPublishableKey('<?php //echo $stripe['live_publishable_key'];?>//');
		//		<?php //endif;?>
		//
		//		// createToken returns immediately - the supplied callback submits the form if there are no errors
		//		Stripe.createToken({
		//			number: $('#stripe_card_num').val(),
		//			cvc: $('#stripe_cvc_code').val(),
		//			exp_month: $('#stripe_expiration_month').val(),
		//			exp_year: $('#stripe_expiration_year').val()
		//		}, function(status, response){
		//
		//			if (response.error) {
		//				$('#stripe_error').html(response.error.message).show();
		//				$('#form-stripe_payments>input[type=button]').show();
		//
		//				$('#stripe-loading').hide();
		//				$('#stripe-details-form').show();
		//				return false;
		//			}
		//			else
		//			{
		//				// token contains id, last4, and card type
		//				var token = response['id'];
		//
		//				// insert the token into the form so it gets submitted to the server
		//				$("#stripe-details-form").html("<input id='stripeToken' type='hidden' name='stripeToken' value='" + token + "'>");
		//				$('#form-stripe_payments').submit();
		//			}
		//		});
		//	}
		//});
	</script>