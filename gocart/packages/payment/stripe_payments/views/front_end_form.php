<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<div id="card-element"
     style="margin-bottom: 20px;padding: 30px;max-width: 400px"></div>
<input type="hidden" name="payment_method" value="Credit Card">
<div id="stripe-loading" style="display:none; text-align:center;">
	<img alt="loading" src="<?php echo theme_img('ajax-loader.gif');?>">
</div>
    <script src="https://js.stripe.com/v3/"></script>
	<script type="text/javascript">

    var publishableKey;
    <?php if($stripe['mode'] == 'test'):?>
    publishableKey = '<?php echo $stripe['test_publishable_key'];?>';
    <?php else: ?>
    publishableKey = '<?php echo $stripe['live_publishable_key'];?>';
    <?php endif;?>
    const appearance = {
        theme: 'night'
    };
    var stripe = Stripe(publishableKey);
    //var secret = 'sk_test_51OIT3CADOb5VKhoH3vXHqfBXbwLzdaxDZ9zMjKBwN1d8KJDygPGEsSh76fiDfK5U27UFFYUtDOMCyWYKkrJUOWmA00CvFGL0HR';
    //var elements = stripe.elements({publishableKey, appearance});
    var elements = stripe.elements();
    var cardElement = elements.create('card');
    cardElement.mount('#card-element');
    var form = document.getElementById('form-stripe_payments');
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        $('#stripe-loading').show();
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
                    $("#form-stripe_payments").append("<input id='module' type='hidden' name='module' value='stripe_payments'>");
                    $("#form-stripe_payments").append("<input id='description' type='hidden' name='description' value='Credit Card'>");
                    $('#form-stripe_payments').submit();
                }
            })
            .catch(function (error) {
                console.error(error);
            });
    });

	</script>