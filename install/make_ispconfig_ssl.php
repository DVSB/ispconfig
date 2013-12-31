<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
function make_ispconfig_ssl_cert() {
	$conf = array(
		'ispconfig_install_dir' => '/tmp/ispconfig'
	);

	$install_dir = $conf['ispconfig_install_dir'];

	$ssl_crt_file = $install_dir.'/interface/ssl/ispserver.crt';
	$ssl_csr_file = $install_dir.'/interface/ssl/ispserver.csr';
	$ssl_key_file = $install_dir.'/interface/ssl/ispserver.key';

	if(!@is_dir($install_dir.'/interface/ssl')) mkdir($install_dir.'/interface/ssl', 0755, true);

	$ssl_pw = substr(md5(mt_rand()),0,6);
	exec("openssl genrsa -des3 -passout pass:$ssl_pw -out $ssl_key_file 4096");
	exec("cat /tmp/ispconfig_cert_input.txt | openssl req -new -passin pass:$ssl_pw -passout pass:$ssl_pw -key $ssl_key_file -out $ssl_csr_file");
	exec("openssl req -x509 -passin pass:$ssl_pw -passout pass:$ssl_pw -key $ssl_key_file -in $ssl_csr_file -out $ssl_crt_file -days 3650");
	exec("openssl rsa -passin pass:$ssl_pw -in $ssl_key_file -out $ssl_key_file.insecure");
	rename($ssl_key_file,$ssl_key_file.'.secure');
	rename($ssl_key_file.'.insecure',$ssl_key_file);

}

make_ispconfig_ssl_cert();