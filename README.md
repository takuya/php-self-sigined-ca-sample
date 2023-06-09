## sample for generate certificate

php で certificate 作成する例。

## 証明書作成の手順

- 発行人の秘密鍵の作成
- 発行人の証明書の作成（自己署名・署名リクエスト）
- 被発行人の秘密鍵の作成
- 被発行人の証明書の作成（自己署名・署名リクエスト）
- 発行人が署名リクエストに署名する。

詳しくは、[サンプルコード](https://github.com/takuya/php-self-sigined-ca-sample/tree/master/sample)を参照

### phpでの秘密鍵の作成
```php
$key = openssl_pkey_new();
openssl_pkey_export($key,$pkey_pem);
```
### 自己署名証明書の作成
```php
$dn = ['C' => 'JP', 'ST' => 'Kyoto', 'L' => 'Kyoto City', 'O' => 'alice'];
$csr = openssl_csr_new( $dn, $key, $conf );
$cert = openssl_csr_sign( $csr, null, $key, 365, $conf );
```

### 署名リクエストへ署名
```php
$opt = [
  'config'          => $path,
  'x509_extensions' => 'usr_cert',
];
$cert = openssl_csr_sign( $userCSR, $issuerCrt, $issuerPkey, $days=30, $opt, time() );
```

### 設定の作成
```php
$conf = [
'config'          => $path,
'req_extensions'  => 'v3_req',
'x509_extensions' => 'v3_req',
];
```

### 注意点
php関数`openssl_csr_sign()` は　`openssl x509 -req`コマンド相当である。

`openssl ca` コマンドとは無関係である。


[openssl_csr_sign](https://github.com/php/php-src/blob/c58c2666a1a405b22ac7de22cd912a7ef2d6a6a6/ext/openssl/openssl.c#L3266)を見てみると、
openssl_csr_sign は　openssl の [X509_sign](https://www.openssl.org/docs/man1.1.1/man3/X509_sign.html)( ` int X509_sign(X509 *x, EVP_PKEY *pkey, const EVP_MD *md);
` ) を用いていることがわかる。

また、`openssl_csr_sign`と`openssl_csr_new` が受け入れる[オプションはかなり制限](https://github.com/php/php-src/blob/master/ext/openssl/openssl.c#L931)されているとわかる。



### subjectAltName の取り扱い。

上記の注意点に留意すると、subjectAltNameは署名時・リクエスト作成時、それぞれ設定が必要がある。
openssl CAでよくある例の、`openssl ca .. copy_extensions=copy`のようなコピーは不可能である。

このあたりは不自由であるが、CA機能未使用のため仕方ないものとして[受け入れるしかない](https://github.com/openssl/openssl/issues/10458)。

リクエスト作成時のSAN指定サンプルは見つかるが、一方でX509_signのSAN指定サンプルは簡単に見つからないので注意したい。

### php でのopenssl.cnf

`php -i | grep ssl  ` で得られる`openssl.cnf` が標準的に使われている。


### openssl.cnf の構造
個人的な見解であるが、次のようになっている。
```ini
[name]
my_name = my_reference
[my_reference]
my_list = @my_list
[my_list]
item = value
item = value
```
どのセクションが使われるのかはopensslのどの機能を使うかによる。署名リクエスト(req)のときは`[req]`セクションが使われる。認証局（CA）のときは、`[ca]`セクションが使われる。

### リクエスト作成時のcnf

リクエスト作成時には `[req]`が使われる。

`distinguished_name = req_distinguished_name`の設定により、`[req_distinguished_name]`を参照することを明示している
`#req_extensions=v3_req` をコメントアウトしているが、phpでは`req_extensions`セクション名はphpから指定するため。openssl コマンドでも同様に、オプションで渡すことが可能。

```ini
[ req ]
distinguished_name = req_distinguished_name
# req_extensions = v3_req
[ req_distinguished_name ]

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
subjectAltName = DNS:mydomain.tld, DNS:seconddomain.tld
nsComment = "Generated Certificate by plain php"
```

### 署名時のopenssl.cnf
署名時には、`X509.extension`を`usr_cert`という名前で指定して、SubjectAltNameを登録している。

phpでは`x509_extensions=usr_cert`とセクション名を関数から指定可能である。openssl コマンドも同様のオプションを渡すことが可能である。
```ini
[ usr_cert ]
basicConstraints=CA:FALSE
nsComment = "[php] OpenSSL Generated Certificate"
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid,issuer
subjectAltName=DNS: replace.me
```

openssl のCA機能であれば、SubjectAltNameを`copy_extension`でまるごとコピーが可能であるが、`openssl x509 req` のときはコピー不可だった。明示する必要があった。



