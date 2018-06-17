<?php

namespace Vodka2;

class SmallProtobufHelper {
    const ID_NUM = 7;
    const TOKEN_NUM = 8;

    public function writeVarint($num){
        $str = '';
        while($num != 0){
            $t = $num & 0x7F;
            $num >>= 7;
            if($num != 0){
                $str .= chr($t | 0x80);
            } else {
                $str .= chr($t);
            }
        }
        return $str;
    }

    public function getQueryMessage($str24){
        $str = "\x10\x00\x1a\x2a\x31\x2d\x64\x61\x33\x39\x61\x33\x65\x65\x35\x65\x36\x62\x34\x62\x30\x64\x33\x32\x35\x35\x62\x66\x65\x66\x39\x35\x36\x30\x31\x38\x39\x30\x61\x66\x64\x38\x30\x37\x30\x39\x22\xe3\x01\x0a\xbf\x01\x0a\x45\x67\x65\x6e\x65\x72\x69\x63\x5f\x78\x38\x36\x2f\x67\x6f\x6f\x67\x6c\x65\x5f\x73\x64\x6b\x5f\x78\x38\x36\x2f\x67\x65\x6e\x65\x72\x69\x63\x5f\x78\x38\x36\x3a\x34\x2e\x34\x2e\x32\x2f\x4b\x4b\x2f\x33\x30\x37\x39\x31\x38\x33\x3a\x65\x6e\x67\x2f\x74\x65\x73\x74\x2d\x6b\x65\x79\x73\x12\x06\x72\x61\x6e\x63\x68\x75\x1a\x0b\x67\x65\x6e\x65\x72\x69\x63\x5f\x78\x38\x36\x2a\x07\x75\x6e\x6b\x6e\x6f\x77\x6e\x32\x0e\x61\x6e\x64\x72\x6f\x69\x64\x2d\x67\x6f\x6f\x67\x6c\x65\x40\x85\xb5\x86\x06\x4a\x0b\x67\x65\x6e\x65\x72\x69\x63\x5f\x78\x38\x36\x50\x13\x5a\x19\x41\x6e\x64\x72\x6f\x69\x64\x20\x53\x44\x4b\x20\x62\x75\x69\x6c\x74\x20\x66\x6f\x72\x20\x78\x38\x36\x62\x07\x75\x6e\x6b\x6e\x6f\x77\x6e\x6a\x0e\x67\x6f\x6f\x67\x6c\x65\x5f\x73\x64\x6b\x5f\x78\x38\x36\x70\x00\x10\x00\x32\x06\x33\x31\x30\x32\x36\x30\x3a\x06\x33\x31\x30\x32\x36\x30\x42\x0b\x6d\x6f\x62\x69\x6c\x65\x3a\x4c\x54\x45\x3a\x48\x00\x32\x05\x65\x6e\x5f\x55\x53\x38\xf0\xb4\xdf\xa6\xb9\x9a\xb8\x83\x8e\x01\x52\x0f\x33\x35\x38\x32\x34\x30\x30\x35\x31\x31\x31\x31\x31\x31\x30\x5a\x00\x62\x10\x41\x6d\x65\x72\x69\x63\x61\x2f\x4e\x65\x77\x5f\x59\x6f\x72\x6b\x70\x03\x7a\x1c\x37\x31\x51\x36\x52\x6e\x32\x44\x44\x5a\x6c\x31\x7a\x50\x44\x56\x61\x61\x65\x45\x48\x49\x74\x64\x2b\x59\x67\x3d\xa0\x01\x00\xb0\x01\x00\xc2\x01";
        $str .= $this->writeVarint(strlen($str24)) . $str24;
        return $str;
    }

    public function decodeRespMessage($msg){
        return $this->findVals($msg);
    }

    private function readVarint(&$data){
        $i = 0;
        $num = 0;
        $len = strlen($data);
        while(true){
            if($i == $len){
                throw new ProtobufException(ProtobufException::NOT_FOUND);
            }
            if(ord($data[$i]) & 0x80){
                $num = $num | ((ord($data[$i]) ^ 0x80) << (7 * $i));
                $i++;
            } else {
                $num = $num | (ord($data[$i]) << (7 * $i));
                break;
            }
        }
        $data = substr($data, $i + 1);
        return $num;
    }

    private function read64(&$data){
        if(strlen($data) < 8){
            throw new ProtobufException(ProtobufException::NOT_FOUND);
        }
        if(PHP_INT_SIZE == 64){
            $str = strval(unpack('P', $data)[1]);
        } else {
            $shs = unpack('v4', $data);
            $str = '';
            while (true) {
                $mod = 0;
                $allZeroes = true;
                for ($i = 4; $i >= 1; $i--) {
                    $t = ($mod << 16) + $shs[$i];
                    $mod = $t % 10000;
                    $shs[$i] = ($t - $mod) / 10000;
                    if ($shs[$i] != 0) {
                        $allZeroes = false;
                    }
                }
                if ($allZeroes) {
                    $str = $mod . $str;
                    break;
                } else {
                    $str = str_pad($mod, 4, '0', STR_PAD_LEFT) . $str;
                }
            }
        }
        $data = substr($data, 8);
        return $str;
    }

    private function readFieldWtype(&$data){
        $num = $this->readVarint($data);
        return ['wtype' => ($num & 0x7), 'field_num' => ($num >> 3)];
    }

    private function findVals($fdata){
        $idFound = false;
        $tokenFound = false;
        while(true){
            if(strlen($fdata) == 0){
                throw new ProtobufException(ProtobufException::NOT_FOUND);
            }
            $fwt = $this->readFieldWtype($fdata);
            switch($fwt['wtype']){
                case 0:
                    $this->readVarint($fdata);
                    break;
                case 1:
                    if($fwt['field_num'] == self::ID_NUM){
                        $idFound = true;
                        $id = $this->read64($fdata);
                    } else if($fwt['field_num'] == self::TOKEN_NUM){
                        $tokenFound = true;
                        $token = $this->read64($fdata);
                    } else {
                        $fdata = substr($fdata, 8);
                    }
                    if($tokenFound && $idFound){
                        return ['id' => $id, 'token' => $token];
                    }
                    break;
                case 2:
                    $len = $this->readVarint($fdata);
                    $fdata = substr($fdata, $len);
                    break;
                default:
                    throw new ProtobufException(ProtobufException::SYMBOL, $fwt['wtype']);
                    break;
            }
        }
    }
}