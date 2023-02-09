<?php


namespace FieldEncryption\Utils;


class DecryptUtils
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
     * @return string|mixed
     */
    public  function decryptAes($value)
    {
        //解密方式：AES 加密
        // -- 加密标识头信息： $aesPre 尾信息：$aesTail
        //解密规则 ：
        // -- 判断是否有加密标识
        // -- 正则匹配获取 $aesTail:后 实际加密长度 $preLen, $tailLen;
        // -- 去除加密标识，截取对应 pre + 解密数据 + tail

        if (trim((string)$value) === "") {
            return $value;
        }

        $aesKey = $this->getAesKey();
        $aesPre = $this->getAesPre();
        $aesTail = $this->getAesTail();

        $valueAesPre = mb_substr($value, 0, mb_strlen($aesPre), 'utf-8');
        if ($valueAesPre !== $aesPre) {
            return $value;
        }

        $preLen = $tailLen = 0;
        preg_match_all("/(?<={$aesTail}).*$/", $value, $matches);
        if (isset($matches[0][0]) && is_string($matches[0][0])) {
            $aesTail .= $matches[0][0];
            $s = explode(',', $matches[0][0]);
            if ($s && count($s) >= 2) {
                $preLen = (int)($s[0] ?? 0);
                $tailLen = (int)($s[1] ?? 0);
            } else {
                return $value;
            }
            $tailLen = $tailLen >= 0 ? $tailLen : 0;
        }
        $valuePre = mb_substr($value, mb_strlen($aesPre) , $preLen, 'utf-8');
        $valueTail = mb_substr($value, -(mb_strlen($aesTail) + $tailLen), $tailLen, 'utf-8');
        $decryptValue = mb_substr($value, mb_strlen($aesPre) + $preLen, -(mb_strlen($aesTail) + $tailLen), 'utf-8');
        return $valuePre . openssl_decrypt($decryptValue, 'AES-128-ECB', $aesKey, 0) . $valueTail;
    }
}
