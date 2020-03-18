<?php
/*
 * Project: CellWaf
 * File: FilterModule
 * Desc: 数据过滤模块
 * Author: MoeGuo H. http://www.avi.moe
 * DateTime: 2019.11.14
 */
//过滤规则文件
function WafUnsetFiles()
{
    //原始文件
    unset($_FILES);
    unset($GLOBALS["_FILES"]);
    unset($HTTP_POST_FILES);
}
//过滤全部内建函数Name
function WafFilterBuildInFunction($value, $key, $data, $waf_gobal_name)
{
    $whileKeyWord = ["header"];
    if (array_key_exists("WafFilterBuildInFunction_ISLoad_" . md5($value), $GLOBALS)) {
        return $value;
    }
    if (!array_key_exists("WafFilterBuildInFunction_KeyWords", $GLOBALS)) {
        $GLOBALS["WafFilterBuildInFunction_KeyWords"] = [];
        foreach (get_defined_functions() as $key => $kv) {
            $GLOBALS["WafFilterBuildInFunction_KeyWords"] = array_merge($GLOBALS["WafFilterBuildInFunction_KeyWords"], $kv);
        }
        $GLOBALS["WafFilterBuildInFunction_KeyWords"] = array_merge($GLOBALS["WafFilterBuildInFunction_KeyWords"], ["eval"]);
        usort($GLOBALS["WafFilterBuildInFunction_KeyWords"], function ($a, $b) {
            return strlen($b) >= strlen($a);
        });
    }
    $keyWords = $GLOBALS["WafFilterBuildInFunction_KeyWords"];
    $touchKey = [];
    $raw_value = $value;
    $isChange = false;
    foreach ($keyWords as $key => $kv) {
        if (strlen($kv) > 3 && strlen($value) >= strlen($kv) && strstr($value, $kv) && !in_array($kv, $whileKeyWord)) {
            $change = str_replace($kv, GenerateRandomString(strlen($kv)), $value);
            if ($value != $change) {
                array_push($touchKey, $kv);
            }
            $value = $change;
        }
    }
    if ($raw_value != $value) {
        WafFilterLog("WafFilterBuildInFunction", $raw_value, $value, $touchKey, $waf_gobal_name);
    }
    $GLOBALS["WafFilterBuildInFunction_ISLoad_" . md5($value)] = true;
    return $value;
}
//通用过滤
function WafFilterSymbol($value, $key, $data, $waf_gobal_name)
{
    //配置
    $keyWords = [];
    //For Symbol
    $keyWords = array_merge($keyWords, explode(" ", "<?php <? ?\> php \$_ \$ () ( %"));
    $touchKey = [];
    //处理数据库
    $raw_value = $value;
    $isChange = false;
    foreach ($keyWords as $key => $kv) {

        $change = str_ireplace($kv, GenerateRandomString(strlen($kv)), $value);
        if ($value != $change) {
            array_push($touchKey, $kv);
        }
        $value = $change;
    }
    if ($raw_value != $value) {

        WafFilterLog("WafFilterSymbol", $raw_value, $value, $touchKey, $waf_gobal_name);
    }
    return $value;
}

//通用过滤
function WafFilterCommon($value, $key, $data, $waf_gobal_name)
{
    //配置
    $keyWords = [];
    //For PHP Code
    $keyWords = array_merge($keyWords, explode(" ", "base64_ file_ include require urlencode"));
    //For Sql
    $keyWords = array_merge($keyWords, explode(" ", "select insert update drop delete"));
    //For Symbol
    $keyWords = array_merge($keyWords, explode(" ", "<?php <? ?\> php \$_ () ( %"));
    $touchKey = [];
    //处理数据库
    $raw_value = $value;
    $isChange = false;
    foreach ($keyWords as $key => $kv) {

        $change = str_ireplace($kv, GenerateRandomString(strlen($kv)), $value);
        if ($value != $change) {
            array_push($touchKey, $kv);
        }
        $value = $change;
    }
    if ($raw_value != $value) {

        WafFilterLog("WafFilterCommon", $raw_value, $value, $touchKey, $waf_gobal_name);
    }
    return $value;
}
//通用过滤
function WafFilterFunctionString($value, $key, $data, $waf_gobal_name)
{
    //配置
    $keyWords = [];
    //For PHP Code
    $keyWords = array_merge($keyWords, explode(" ", "eval system preg_replace create_function array_map call_user_func call_user_func_array assert dumpfile outfile load_file rename extractvalue updatexml name_const multipoint"));
    $keyWords = array_merge($keyWords, explode(" ", "base64_ file_ include require urlencode"));
    //For Sql
    $keyWords = array_merge($keyWords, explode(" ", "select insert update drop delete"));
    //For Symbol
    $keyWords = array_merge($keyWords, explode(" ", "<?php <? ?\> :{ php \$_"));
    //处理数据库
    $raw_value = $value;
    $isChange = false;
    $touchKey = [];
    foreach ($keyWords as $key => $kv) {
        $change = str_ireplace($kv, GenerateRandomString(strlen($kv)), $value);
        $value = $change;
        if ($value != $change) {
            array_push($touchKey, $kv);
        }
    }
    if ($raw_value != $value) {

        WafFilterLog("WafFilterFunctionString", $raw_value, $value, $touchKey, $waf_gobal_name);
    }
    return $value;
}
//Base64过滤
function WafFilterBase64($value, $key, $data, $waf_gobal_name)
{
    $CellWafFilteFunctionList = ["WafFilterFlagKeywords", "WafFilterFunctionString"];
    $output_array = [];
    $_change_value = $value;
    if (preg_match('/[a-zA-Z0-9\+\=]{1,}/i', $value, $output_array) > 0) {
        foreach ($output_array  as $key => $ov) {
            $raw_value = base64_decode($ov);
            $change_value = $raw_value;
            foreach ($CellWafFilteFunctionList as $fnkey => $fn) {
                $change_value = $fn($change_value, $value, [$ov => $raw_value], $waf_gobal_name);
            }
            if ($change_value != $raw_value) {
                $_change_value = str_replace($ov, base64_encode($change_value), $_change_value);
            }
        }
    }
    if ($_change_value != $value) {
        WafFilterLog("WafFilterBase64", $value, $_change_value, [], $waf_gobal_name);
        $value = $_change_value;
    }

    return $value;
}

//AWD CTF 过滤Flag
function WafFilterFlagKeywords($value, $key, $data, $waf_gobal_name)
{
    $change_value = preg_replace('/(f(.*?)l(.*?)a(.*?)g$)/i', GenerateRandomString(rand(10, 100)), $value);
    if ($value != $change_value) {
        $touchKey = [];
        preg_match('/(f(.*?)l(.*?)a(.*?)g$)/i', $value,  $touchKey);
        WafFilterLog("WafFilterFlagKeywords", $value, $change_value, $touchKey, $waf_gobal_name);
        $GLOBALS[$waf_gobal_name]["_BaseInfo"]["IsDanger"] = true;
    }
    $value = $change_value;
    return $value;
}

function WafFilterDataLoop($data, $filterFn, $waf_gobal_name)
{
    foreach ($data as $key => $value) {
        $type = gettype($value);
        if ($type == "array") {
            $data[$key] = WafFilterDataLoop($value, $filterFn, $waf_gobal_name);
        } else if ($type == "object") {
            unset($data[$key]);
        } else {
            $data[$key] = $filterFn($value, $key, $data, $waf_gobal_name);
        }
    }
    return $data;
}

//生成随机字符串
function GenerateRandomString($length)
{
    $characters = '0123456789abcdeghijklmnopqrstuvwxyzABCDEGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}



function WafFilterLog($actionName, $beforeData, $afterData, $touchKeys, $waf_gobal_name)
{
    array_push($GLOBALS[$waf_gobal_name]["_FilterAction"], [
        "actionName" => $actionName,
        "touchKeys" => $touchKeys,
        "beforeData" => $beforeData,
        "afterData" => $afterData
    ]);
}


function WafArrayFilter($CellWafFilterTarget, $CellWafFilteFunctionList, $waf_gobal_name)
{
    //执行过程
    foreach ($CellWafFilterTarget as $key => $value) {
        foreach ($CellWafFilteFunctionList as $fnkey => $fn) {
            $CellWafFilterTarget[$key] = WafFilterDataLoop($CellWafFilterTarget[$key], $fn, $waf_gobal_name);
        }
    }
}

//Waf过滤流程
function WafFilterStart($waf_gobal_name)
{
    //清除文件
    WafUnsetFiles();
    //代码关键词过滤
    WafArrayFilter(
        [
            &$_POST,
            &$_GET,
            &$_REQUEST,
            &$GLOBALS["_POST"],
            &$GLOBALS["_GET"],
            &$GLOBALS["_REQUEST"]
        ],
        [
            "WafFilterBuildInFunction",
            "WafFilterSymbol"
        ],
        $waf_gobal_name
    );
    //详细参数过滤
    WafArrayFilter(
        [
            &$_POST,
            &$_GET,
            &$_REQUEST,
            &$_COOKIE,
            &$GLOBALS["_POST"],
            &$GLOBALS["_GET"],
            &$GLOBALS["_REQUEST"]
        ],
        [
            "WafFilterCommon",
            "WafFilterFlagKeywords",
            "WafFilterBase64"
        ],
        $waf_gobal_name
    );
}

function WafFilter($waf_toekn)
{
    $waf_gobal_name = "CellWaf_" . md5($waf_toekn);
    WafFilterStart($waf_gobal_name);
}
