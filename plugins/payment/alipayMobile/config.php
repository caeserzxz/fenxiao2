<?php
return array(
    'code'=> 'alipayMobile',
    'name' => '手机网站支付宝',
    'version' => '1.0',
    'desc' => '手机端网站支付宝 ',
    'icon' => 'logo.jpg',
    'scene' =>1,  // 使用场景 0 PC+手机 1 手机 2 PC
    'config' => array(
        array('name' => 'alipay_account','label'=>'支付宝帐户',           'type' => 'text',   'value' => ''),
        array('name' => 'alipay_key','label'=>'交易安全校验码',               'type' => 'text',   'value' => ''),
        array('name' => 'app_id','label'=>'APPID',           'type' => 'text',   'value' => ''),
        array('name' => 'charset','label'=>'编码格式',           'type' => 'text',   'value' => ''),
        array('name' => 'sign_type','label'=>'签名方式',           'type' => 'text',   'value' => ''),
        array('name' => 'gatewayUrl','label'=>'支付宝网关',           'type' => 'text',   'value' => ''),
        array('name' => 'merchant_private_key','label'=>'商户私钥，您的原始格式RSA私钥',           'type' => 'textarea',   'value' => ''),
        array('name' => 'alipay_public_key','label'=>'支付宝公钥',           'type' => 'textarea',   'value' => ''),
    ),
);