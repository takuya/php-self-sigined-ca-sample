<?php

$openssl_conf = <<<EOS
[ req ]
distinguished_name = req_distinguished_name

[ req_distinguished_name ]

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
subjectAltName = DNS:mydomain.tld, DNS:seconddomain.tld
nsComment = "Generated Certificate by plain php"
EOS;

$path = tempnam( sys_get_temp_dir(), 'openssl.conf-' );
file_put_contents($path,$openssl_conf);
$conf = [
  'config'          => $path,
  'req_extensions'  => 'v3_req',
  'x509_extensions' => 'v3_req',
];
$dn = ['C' => 'JP', 'ST' => 'Kyoto', 'L' => 'Kyoto City', 'O' => 'alice'];
$key = openssl_pkey_new();

$csr = openssl_csr_new( $dn, $key, $conf );
$cert = openssl_csr_sign( $csr, null, $key, 365, $conf );
openssl_x509_export( $cert, $certout, false );
echo $certout;


