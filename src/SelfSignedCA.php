<?php

namespace Takuya\PhpSelfSiginedCaSample;

use Sop\X509\CertificationRequest\CertificationRequest;
use Sop\CryptoEncoding\PEM;
use Sop\X509\Certificate\Extension\SubjectAlternativeNameExtension;
use Sop\X509\Certificate\Extension\Extension;
use Sop\X509\GeneralName\GeneralNames;
use Sop\X509\GeneralName\GeneralName;

class SelfSignedCA {
  public function __construct () {
    $this->serial = 0;
    openssl_pkey_export( openssl_pkey_new(), $pkey );
    $key_object = openssl_pkey_get_private( $pkey );
    openssl_csr_export( openssl_csr_new( [
        'CN' => 'OreORe',
        'C'  => 'Au',
        'ST' => 'SomeState',
      ]
      , $key_object ), $csr );
    $crt = openssl_csr_sign( $csr, null, $pkey, 30, null, $this->serial++ );
    $this->cert = $crt;
    $this->pkey = $pkey;
    $this->days = 30;
    $this->opt = ['req_extensions' => 'v3_req', 'x509_extensions' => 'v3_req'];
    openssl_x509_export_to_file( $crt, 'ca.key' );
    openssl_pkey_export_to_file( $pkey, 'priv.key' );
  }
  
  protected function sign ( $csr, $opt, $days = null ) {
    $cnf = $this->sampleCA();
    
    $san = $this->openssl_csr_get_san( $csr );
    $cnf = preg_replace( '/subjectAltName.+$/', "subjectAltName ={$san}", $cnf );
    $path = $this->save_openssl_conf( $cnf );
    $opt = [
      'config'          => $path,
      'x509_extensions' => 'usr_cert',
    ];
    $cert = openssl_csr_sign( $csr, $this->cert, $this->pkey, $days, $opt, $this->serial++ );
    if ( !$cert ) {
      $str = openssl_error_string();
      throw new \RuntimeException( $str );
    }
    unlink( $path );
    return $cert;
  }
  
  protected function openssl_csr_get_san ( $csr ) {
    $csr = CertificationRequest::fromPEM( PEM::fromString( $csr ) );
    $info = $csr->certificationRequestInfo();
    $oid = Extension::OID_SUBJECT_ALT_NAME;
    /** @var SubjectAlternativeNameExtension $ext */
    $ext = $info->attributes()->extensionRequest()->extensions()->get( $oid );
    /** @var GeneralNames $names */
    $names = $ext->names();
    $dns = array_map( fn( $e ) => $e->string(), $names->allOf( GeneralName::TAG_DNS_NAME ) );
    $ip = array_map( fn( $e ) => $e->string(), $names->allOf( GeneralName::TAG_IP_ADDRESS ) );
    $san = join( ',', array_merge(
      array_map( fn( $d ) => "DNS:$d", $dns ),
      array_map( fn( $d ) => "IP:$d", $ip ) ) );
    
    return $san;
  }
  
  public function sampleCA () {
    $str = <<<EOS
    [ usr_cert ]
    basicConstraints=CA:FALSE
    nsComment = "[php] OpenSSL Generated Certificate"
    subjectKeyIdentifier=hash
    authorityKeyIdentifier=keyid,issuer
    subjectAltName=DNS: replace.me
    EOS;
    return $str;
  }
  
  public function createCSR ( $commonName, string|\OpenSSLAsymmetricKey $key, array $domains, array $ip_addrs = [],  ) {
    $dn = ['C' => 'Jp', 'ST' => 'Kyoto', 'L' => 'Kyoto City', 'O' => $commonName, 'CN' => $commonName];
    $cnf = $this->sample_SAN();
    $san = join( ',', array_merge(
      array_map( fn( $d ) => "DNS:$d", $domains ),
      array_map( fn( $d ) => "IP:$d", $ip_addrs ) ) );
    $cnf = preg_replace( '/subjectAltName.+/', 'subjectAltName = '.$san, $cnf );
    $path = $this->save_openssl_conf( $cnf );
    $this->opt['config'] = $path;
    $csr = openssl_csr_new( $dn, $key, $this->opt );
    if ( !$csr ) {
      $str = openssl_error_string();
      throw new \RuntimeException( $str );
    }
    openssl_csr_export( $csr, $str );
    unlink( $path );
    return $str;
  }
  
  protected function save_openssl_conf ( $conf ) {
    $path = tempnam( sys_get_temp_dir(), 'openssl.conf-' );
    file_put_contents( $path, $conf );
    return $path;
  }
  
  public function createNewCertificate ( $key = null,
                                         array $dn = [],
                                         array $options = null ) {
    $key = $key ?? openssl_pkey_new();
    $dn = $dn ?: ['C' => 'JP', 'ST' => 'Kyoto', 'L' => 'Kyoto City', 'O' => 'alice', 'CN' => 'alice.lan'];
    $conf = [
      'config'          => $this->save_openssl_conf( $this->sample_SAN() ),
      'req_extensions'  => 'v3_req',
      'x509_extensions' => 'v3_req',
    ];
    $csr = openssl_csr_new( $dn, $key, $conf );
    openssl_csr_export( $csr, $str );
    $cert = $this->sign( $csr, $conf );
    openssl_x509_export( $cert, $str );
    openssl_pkcs12_export( $cert, $pkcs12, $key, '' );
    unlink( $conf['config'] );
    return $pkcs12;
  }
  
  public function issueCert ( $csr ) {
    $cert = $this->sign( $csr, $this->opt, $this->days );
    openssl_x509_export( $cert, $str );
    return $str;
  }
  
  public function sample_SAN () {
    $san_conf = <<<EOS
    [ req ]
    distinguished_name = req_distinguished_name
    req_extensions = v3_req
    
    [ req_distinguished_name ]
    
    [ v3_req ]
    basicConstraints = CA:FALSE
    keyUsage = nonRepudiation, digitalSignature, keyEncipherment
    subjectAltName = DNS:mydomain.tld, DNS:seconddomain.tld
    nsComment = "Generated Certificate by php takuya"
    
    EOS;
    return $san_conf;
  }
  
  
}