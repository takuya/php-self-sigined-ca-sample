<?php


use Takuya\PhpSelfSiginedCaSample\SelfSignedCA;

require __DIR__.'/../vendor/autoload.php';

$ca = new SelfSignedCA();

$key =  openssl_pkey_new();
openssl_pkey_export($key,$pkey);

$domains = ['alice.tld','bob.tld'];
$IPAddrs = ['192.168.1.1'];
$csr = $ca->createCSR('alice',$key ,$domains,$IPAddrs);
file_put_contents('server.csr',$csr);
echo `openssl req -noout -text < server.csr`;

$cert = $ca->issueCert($csr);
openssl_x509_export($cert,$str);
file_put_contents('server.crt',$str);
echo `openssl x509 -noout -text < server.crt`;


openssl_pkcs12_export( $cert, $pkcs12, $key, '' );
file_put_contents('server.p12',$pkcs12);
echo `openssl pkcs12   -passin pass:'' -nodes < server.p12`;

unlink('server.csr');
unlink('server.crt');
unlink('server.p12');
