<?php


namespace FieldEncryption\Utils;


class EncryptionUtils
{
    public function __construct($aesKey, $aesPre, $aesTail) {
        $this->aesKey = $aesKey;
        $this->aesPre = $aesPre;
        $this->aesTail = $aesTail;
    }

    protected $aesKey;
    protected $aesPre;
    protected $aesTail;

    public function getAesKey()
    {
        return $this->aesKey;
    }

    public function getAesPre()
    {
        return $this->aesPre;
    }

    public  function getAesTail()
    {
        return $this->aesTail;
    }

    /**
     * @param $value
     * @param $preLen
     * @param $encryptionFieldLen
     * @return mixed
     */
    public function encryptionAes($value, $preLen, $encryptionFieldLen)
    {
        if (trim((string)$value) === '') {
            return $value;
        }

        $aesKey = $this->getAesKey();
        $aesPre = $this->getAesPre();
        $aesTail = $this->getAesTail();
        $valeAesPre = mb_substr($value, 0, mb_strlen($aesPre), 'utf-8');

        //数据若加密则进行直接返回
        if ($valeAesPre === $aesPre) {
            return $value;
        }

        //加密方式：AES 加密
        // -- 加密标识头信息： $aesPre 尾信息：$aesTail
        //加密规则 ：
        // -- 保留字符串前几位 $preLen 加密中间几位 $encryptionFieldLen  (保留最后几位 $tailLen)
        // -- 由于加密位数不确定，本加密规则由以下设定：
        // -- -- 当待加密数据为空时 不进行加密，不需要添加加密标识
        // -- -- 所有加密数据加密过后都需要加上加密标识: $aesPre
        // -- -- 当待加密数据小于$preLen 完全进行加密
        // -- -- 将加密字段$preLen,$taiLen放入 "$aesTail:"后

        if (mb_strlen($value) <= $preLen) {
            return $aesPre . openssl_encrypt($value, 'AES-128-ECB', $aesKey, 0) . $aesTail . "0,0";
        }
        $tailLen =  mb_strlen($value) - $preLen - $encryptionFieldLen;
        $tailLen = $tailLen >= 0 ? $tailLen : 0;

        $valuePre = mb_substr($value, 0, $preLen, 'utf-8');
        $valueEncryptionField = mb_substr($value, $preLen, $encryptionFieldLen, 'utf-8');
        $valueTail = mb_substr($value, $preLen + $encryptionFieldLen, mb_strlen($value) - $preLen - $encryptionFieldLen , 'utf-8');
        $encrypt = openssl_encrypt($valueEncryptionField, 'AES-128-ECB', $aesKey, 0);
        return $aesPre . $valuePre . $encrypt . $valueTail . $aesTail . "$preLen,$tailLen";
    }
}
