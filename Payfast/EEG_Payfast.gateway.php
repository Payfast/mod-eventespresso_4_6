<?php

if ( !defined( 'EVENT_ESPRESSO_VERSION' ) ) 
{
    exit ( 'No direct script access allowed' );
}


class EEG_Payfast extends EE_Offsite_Gateway
{
    protected $_payfast_merchant_id = '';
    protected $_payfast_merchant_key = '';
    protected $_payfast_passphrase = '';

    protected $_currencies_supported = array('ZAR');

    public function __construct() 
    {
        $this->set_uses_separate_IPN_request( true ) ;
        parent::__construct();
    }

    public function set_settings($settings_array)
    {
        parent::set_settings($settings_array);
        if ($this->_debug_mode)
        {
            $this->_gateway_url = 'https://sandbox.payfast.co.za/eng/process';
        }
        else
        {
            $this->_gateway_url = 'https://www.payfast.co.za/eng/process';
        }
    }


    public function set_redirection_info($payment, $billing_info = array(), $return_url = NULL, $notify_url = NULL, $cancel_url = NULL)
    {
    //    $redirect_args = array();
        $transaction = $payment->transaction();
        $primary_registrant = $transaction->primary_registration();
        $primary_attendee = $primary_registrant->attendee();

        $redirect_args = array(
            'merchant_id' => ( $this->_debug_mode ? '10000861' : $this->_payfast_merchant_id ),
            'merchant_key' => ( $this->_debug_mode ? '1pelravrwmo8e' : $this->_payfast_merchant_key ),
            'return_url' => $return_url,
            'cancel_url' => /*$cancel_url*/site_url( $path, $scheme ),
            'notify_url' => $notify_url,
            'name_first' => $primary_attendee->fname(),
            'name_last' => $primary_attendee->lname(),
            'email_address' => $primary_attendee->email(),
            'amount' => $payment->amount(),
            'item_name' => $primary_registrant->reg_code(),
        );

        $pfOutput = '';
        // Create output string
        foreach( $redirect_args as $key => $val )
        {
            $pfOutput .=$key . '=' . urlencode(trim($val)) . '&';
        }

        $passPhrase = $this->_payfast_passphrase;
        if ( empty( $passPhrase ) || $this->_debug_mode )
        {
            $pfOutput = substr( $pfOutput, 0, -1 );
        }
        else
        {
            $pfOutput = $pfOutput."passphrase=".urlencode( $passPhrase );
        }

        $redirect_args['signature'] = md5( $pfOutput );
        $redirect_args['user_agent'] = 'EventEspresso 4.8';

        $redirect_args = apply_filters("FHEE__EEG_Payfast__set_redirection_info__arguments", $redirect_args);

        $payment->set_redirect_url( $this->_gateway_url );
        $payment->set_redirect_args( $redirect_args );

        return $payment;
    }

    public function handle_payment_update( $update_info, $transaction )
    {
       include_once( 'lib/payfast_common.inc' );

        pflog( 'PayFast ITN call received' );

        $pfError = false;
        $pfErrMsg = '';
        $pfDone = false;
        $pfData = array();
        $pfParamString = '';

        //// Notify PayFast that information has been received
        if ( $_GET['action'] != "process_ipn" && !$pfError && !$pfDone )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }
        
        if ( !$pfError && !$pfDone )
        {
            pflog('Get posted data');

            // Posted variables from ITN
            $pfData = pfGetData();

            pflog( 'PayFast Data: ' . print_r( $pfData, true ) );

            if ( $pfData === false )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        //// Verify security signature
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify security signature' );

            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }

        //// Verify source IP (If not in debug mode)
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify source IP' );

            if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }

        $pfHost = $PayFast_sandbox ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';

        //// Verify data received
        if( !$pfError )
        {
            pflog( 'Verify data received' );

            $pfValid = pfValidData( $pfHost, $pfParamString );

            if( !$pfValid )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        //// Check data against internal order
        if( !$pfError && !$pfDone )
        {
            pflog( 'Check data against internal order' );

            // Check order amount
            if( !pfAmountsEqual( $pfData['amount_gross'], $payment->amount() ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }

        }

        $payment = $this->_pay_model->get_payment_by_txn_id_chq_nmbr( $pfData['pf_payment_id'] );
        if ( !$payment )
        {
            $payment = $transaction->last_payment();
        }
        //ok, then validate the IPN. Even if we've already processed this payment, let PayFast know we don't want to hear from them anymore!
        if ( !$this->validate_ipn( $update_info, $payment ) )
        {
            //huh, something's wack... the IPN didn't validate. We must have replied to the IPN incorrectly,
            $this->log( sprintf( __( "IPN failed validation", "event_espresso" ) ), $transaction );
            return $payment;
        }


        //ok, well let's process this payment then!
        if ( !$pfError && !$pfDone )
        {
            pflog('check order and update payment status');

            if ($pfData['payment_status'] == 'COMPLETE') {
                pflog('- Complete');
                pflog('PayFast transaction id: ' . $pfData['pf_payment_id']);
                $status = $this->_pay_model->approved_status();//approved
                $gateway_response = __('Your payment is approved.', 'event_espresso');
            } elseif ($pfData['payment_status'] == 'Pending') {
                $status = $this->_pay_model->pending_status();//approved
                $gateway_response = __('Your payment is in progress. Another message will be sent when payment is approved.', 'event_espresso');
            } else {
                $status = $this->_pay_model->declined_status();//declined
                $gateway_response = __('Your payment has been declined.', 'event_espresso');
            }
        }

        if( $pfError )
        {
            pflog( 'Error occurred: '. $pfErrMsg );
        }

        //check if we've already processed this payment
        if ( !empty( $payment ) )
        {
            //payment exists. if this has the exact same status and amount, don't bother updating. just return
            if ( $payment->status() == $status && $payment->amount() == $pfData['amount_gross'] )
            {
                //echo "duplicated ipn! dont bother updating transaction foo!";
                $this->log( array (
                    'message' => sprintf( __( 'It appears we have received a duplicate IPN from PayFast for payment %d', 'event_espresso' ), $payment->ID() ),
                    'payment' => $payment->model_field_array(),
                    'IPN data' => $update_info ),
                    $payment );
            }
            else
            {
//				$this->_debug_log( "<hr>Existing IPN for this PayFast transaction, but it\'s got some new info. Old status:".$payment->STS_ID().", old amount:".$payment->amount());
                $payment->set_status( $status );
                $payment->set_amount( $pfData['amount_gross'] );
                $payment->set_gateway_response( $gateway_response );
                $payment->set_details( $update_info );
                $this->log( array(
                    'message' => sprintf( __( 'Updated payment either from IPN or as part of POST from PayFast', 'event_espresso' ) ),
                    'payment' => $payment->model_field_array(),
                    'IPN_data' => $update_info ),
                    $payment );
            }
        }
        return $payment;
    }

    public function validate_ipn($update_info,$payment)
    {
        //allow us to skip validating IPNs with payfast (useful for testing)
        if ( apply_filters( 'FHEE__EEG_PayFast__validate_ipn__skip', true ) )
        {
            return true;
        }
        if( $update_info === $_REQUEST )
        {
            //we're using the $_REQUEST info... except we can't use it because it has issues with quotes
            // Reading POSTed data directly from $_POST causes serialization issues with array data in the POST.
            // Instead, read raw POST data from the input stream.
            // @see https://gist.github.com/xcommerce-gists/3440401
            $raw_post_data = file_get_contents('php://input');
            $raw_post_array = explode('&', $raw_post_data);
            $update_info = array();
            foreach ( $raw_post_array as $keyval )
            {
                $keyval = explode ( '=', $keyval );
                if ( count( $keyval ) == 2 )
                    $update_info[$keyval[0]] = urldecode($keyval[1]);
            }
        }

//		$update_info_from_post_only = array_diff_key($update_info, $_GET);
//		$response_post_data=$update_info_from_post_only + array('cmd'=>'_notify-validate');
        $result= wp_remote_post( $this->_gateway_url, array( 'body' => $req, 'sslverify' => false, 'timeout' => 60 ) );

        if ( ! is_wp_error( $result ) && array_key_exists( 'body',$result ) && strcmp( $result['body'], "VERIFIED" ) == 0 )
        {
            return true;
        }
        else
        {
            $payment->set_gateway_response( sprintf( __( "IPN Validation failed! PayFast responded with '%s'", "event_espresso" ),$result['body'] ) );
        //    $payment->set_details( array( 'REQUEST'=>$update_info,'VALIDATION_RESPONSE'=>$result ) );
            $payment->set_status( EEM_Payment::status_id_failed );
            return false;
        }
    }

    public function update_txn_based_on_payment( $payment )
    {
        $update_info = $payment->details();
        $redirect_args = $payment->redirect_args();
        $transaction = $payment->transaction();
        if ( !$transaction )
        {
            $this->log( __( 'Payment with ID %d has no related transaction, and so update_txn_based_on_payment couldnt be executed properly', 'event_espresso' ), $payment );
            return;
        }
        if ( !is_array( $update_info ) || ! isset( $update_info[ 'mc_shipping' ] ) || !isset( $update_info[ 'tax' ] ) )
        {
            $this->log(
                array( 'message' => __( 'Could not update transaction based on payment because the payment details have not yet been put on the payment. This normally happens during the IPN or returning from PayFast', 'event_espresso' ),
                    'payment' => $payment->model_field_array() ),
                $payment );
            return;
        }

        $grand_total_needs_resaving = FALSE;

        if( $grand_total_needs_resaving )
        {
            $transaction->total_line_item()->save_this_and_descendants_to_txn( $transaction->ID() );
        }
        $this->log( array(
            'message' => __( 'Updated transaction related to payment', 'event_espresso' ),
            'transaction (updated)' => $transaction->model_field_array(),
            'payment (updated)' => $payment->model_field_array(),
        //    'use_PayFast_shipping' => $this->_PayFast_shipping,
        //    'use_PayFast_tax' => $this->_PayFast_taxes,
            'grand_total_needed_resaving' => $grand_total_needs_resaving,),

            $payment);
    }
}
