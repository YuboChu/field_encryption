#基于laravel实现数据库敏感字段加密
## 原理：
#### 1.替代db.factory ,将对应的 factory 指向自定义 FieldEncryptionConnectionFactory, 将对应的mysqlConnection 指向自定义FieldEncryptionMysqlConnection,最终将Builder替换成自定义FieldBuilder
#### 2.改写 FieldBuilder 中get,insert,update 等方法，将需要加密的字段进行加密，读取时将加密字段进行解密
## 注意：
#### 并不是所有写法都适合，比如Raw，whereRaw等原生写法，也不支持sql函数对该字段进行处理，当相关业务涉及到上述写法，建议修改写法或者加字段加密成加密字段再添加
##### 加密:
    //$rule => 配置保留前置位数和加密
    $rule = ['pre_len' => 0, 'encryption_field_len' => 20000];
    /** @var \FieldEncryption\Utils\EncryptionUtils $encryption */
    $encryption = app(\FieldEncryption\Utils\EncryptionUtils::class);
    $value = $encryption->encryptionAes($value, $rule['pre_len'], $rule['encryption_field_len']);
##### 解密:
    /** @var \FieldEncryption\Utils\DecryptUtils $decrypt */
    $decrypt = app(\FieldEncryption\Utils\DecryptUtils::class);
    $value = $decrypt->decryptAes($value);
## 使用方法：
#### 1.在laravel中app/config/app.php 中 providers 添加
     \FieldEncryption\Providers\DatabaseServiceCustomProvider::class,
     \FieldEncryption\Providers\FieldEncryptionProvider::class,
注意要放到 Illuminate\Database\DatabaseServiceProvider::class下面的位置，相当于覆盖
#### 2.将 vendor/chuyubo/field_encryption/config/field_encryption.php 复制到app/config/文件夹下
##### aes_key 加密密钥，保证不进行修改
##### aes_pre 加密后的字段前缀标识，不要为空
##### aes_tail 加密后的字段后缀标识，不要为空
##### table_tmp 临时表后缀，要复杂不要与原表名后缀一样，不要为空
##### table_tmp_switch 临时表开关，true|false，跑同步脚本的时候需要打开，其他情况下关闭
##### field 表字段配置 二维数组，内容如下
    [
        [
            'table' => '', //table 表名称
            'column' => '', //加密列
            'rule' => '', //加密规则，与下面rules 相对应
        ]
    ]
##### rules 加密规则：
    [
        //键值对应field中的rule
        'default' => [
            'pre_len' => 0, //加密字段前置保留位数，适配一些模糊查询
            'tail_len' => 0, //加密字段后置保留位数，目前并不管用
            'encryption_field_len' => 20000, //加密位数，目前根据前置位数+加密位数来决定加密成什么样子
        ],
    ]
#### 3.将数据同步成加密数据的加密脚本
    php artisan sys:filed-encryption
可以根据自己的需求改写，脚本所在位置
<br>vendor/chuyubo/field_encryption/src/Command/SysCommand.php
<br>原理：生成临时表进行插入加密后的数据然后关联替换
#### 4.最终加密后效果：
field_NResPuJT+vjlfgspUqNINah5PsJwwJ2yA6shz9WsSSS=_encryption0,0
<br/>解析：'field _'为前置加密标识，'_encryption'为后置加密标识, 0,0 前置保留位数0，后置保留位数0





