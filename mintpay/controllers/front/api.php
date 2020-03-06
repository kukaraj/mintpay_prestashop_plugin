<?php


class MintPayApiModuleFrontController extends ModuleFrontController
{
    /**
     * Processa os dados enviados pelo formulário de pagamento
     */
    

    public function postProcess()
    {
    	//echo '------------------------------------------------------------';

    	$cart = $this->context->cart;
    	$customer = new Customer(intval($cart->id_customer));
    	$delivery = new Address(intval($cart->id_address_delivery));
    	$invoice = new Address(intval($cart->id_address_invoice));
    	$carrier = new Carrier(intval($cart->id_carrier));
        $products = $cart->getProducts();

        $orderItems = array();
    	

    	foreach ($products as $itemAsRow) {
            
            $item = (object)$itemAsRow;

            $tmpItem = array(
                'name' => $item->name,
                'product_id' => $item->id_product,
                'sku' => $item->reference,
                'quantity' => $item->cart_quantity,
                'unit_price' => $item->price,
                'created_date' => $item->date_add,
                'updated_date'=> $item->date_upd,
                'discount' => $item->reduction
            );

            
            array_push($orderItems, $tmpItem);
        }


        //echo json_encode($orderItems);
        $redirectHashKey = Configuration::get('mintpay_encrypt_key');
        $orderHash = hash('sha256', strval($cart->id).$redirectHashKey).hash('sha256', strval($cart->id_customer).$redirectHashKey);

    	$data = array(
            'success_url' => Context::getContext()->link->getModuleLink('mintpay', 'validation', ['orederId'=>$cart->id, 'hash'=>$orderHash]),
            'fail_url' =>  _PS_BASE_URL_.__PS_BASE_URI__.'index.php?controller=order&step=1',
            'merchant_id' => Configuration::get('mintpay_merchant_id'),
            'order_id' => $cart->id,
            'total_price' => number_format($cart->getOrderTotal(true, 3), 2, '.', ''),
            'discount' => '0',
            'customer_id' =>  $customer->is_guest == 1 ? null : $customer->id,
            'customer_email' =>  $customer->email,
            'customer_telephone' => $delivery->phone == '' ? null : $delivery->phone,
            'ip' => "'".$_SERVER['REMOTE_ADDR']."'",
            'x_forwarded_for' => "'".$_SERVER['REMOTE_ADDR']."'",
            'delivery_street' =>  $delivery->address1 . ' ' . $delivery->address2,
            'delivery_region' => $delivery->city,
            'delivery_postcode' => $delivery->postcode,
            'cart_created_date' => $cart->date_add,
            'cart_updated_date'=> $cart->date_upd,
            'products' => $orderItems,
            
        );

        $data = json_encode($data);


        

        $header = array("content-type:application/JSON","Authorization:Token ".Configuration::get('mintpay_auth_token'));
        $url = 'https://mintpay.lk/user-order/api/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $respStr = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($respStr, TRUE);


        //die(Context::getContext()->link->getModuleLink('mintpay', 'validation', ['orederId'=>$cart->id, 'hash'=>$orderHash]));

        if ($resp === null) {
            //throw new Twocheckout_Error("cURL call failed", "403");
            Tools::redirect('index.php?controller=order&step=1');
           
        } else {

            if(isset($resp['message']) && $resp['message']=='Success'){

                $codeHash = hash('sha256', $resp['data'].'dPxWj9zJ2AQhCtjjTVdKrZNEs2zT2H3kHBxdhCchaSHXNNt66ZNDyYAr5sKfAsSgTrjeTZMxJw6jNmexPykqaD6hMBpuxjN5849y');
                $sql = "INSERT INTO "._DB_PREFIX_."mintpay(code, hash) VALUES('".$resp['data']."', '".$codeHash."');";
                $result=Db::getInstance()->Execute($sql);
                if($result){
                    $redirectPageLink = Context::getContext()->link->getModuleLink('mintpay', 'redirect', ['resp'=>$codeHash]);
                    Tools::redirect($redirectPageLink);
                }
                else{
                    Tools::redirect('index.php?controller=order&step=1');
                }
            }
            else{
                Tools::redirect('index.php?controller=order&step=1');
            }
            
        }

    }

}