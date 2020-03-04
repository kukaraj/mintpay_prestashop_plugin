<?php

/**
 * <ModuleName> => cheque
 * <FileName> => validation.php
 * Format expected: <ModuleName><FileName>ModuleFrontController
 */
class MintPayRedirectModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {

        $hash = pSQL(Tools::getValue('resp'));

        $sql = "SELECT * FROM "._DB_PREFIX_."mintpay WHERE hash='".$hash."'";
        $result=Db::getInstance()->ExecuteS($sql);

        $respCode = null;
        $url = null;

        if(sizeOf($result)==1){
            
            // DELETE the record from database instantly after using for redirect

            $respCode = $result[0]['code'];
            $url = "https://mintpay.lk/user-order/login/";

            $sql = "DELETE FROM "._DB_PREFIX_."mintpay WHERE id='".$result[0]['id']."'";
            $result=Db::getInstance()->Execute($sql);

            
        }


        // In the template, we need the vars paymentId & paymentStatus to be defined
        $this->context->smarty->assign(
            array(
            'responseCode' => $respCode, // Retrieved from GET vars
            'redirectUrl' => $url,
            ));


        // Will use the file modules/cheque/views/templates/front/validation.tpl
        $this->setTemplate('module:mintpay/views/templates/front/redirect.tpl');
    }


}