<?php
/*
 * Project: CellWaf
 * File: LibModule
 * Desc: 防火墙核心库
 * Author: MoeGuo H. http://www.avi.moe
 * DateTime: 2019.11.14
 */
//获取协议
function GetProtocol()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return "https://";
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return "https://";
    } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return "https://";
    }
    return "http://";
}

//时间戳
function GetNowTime()
{
    list($usec, $sec) = explode(" ", microtime());
    $result = ((float) $usec + (float) $sec);
    for ($i = 0; $i < 16 - strlen($result); $i += 1) {
        $result .= "0";
    }
    $result .= rand(10000, 99999);
    return $result;
}

//获取地址
function GetIP()
{
    global $ip;
    if (getenv("HTTP_CLIENT_IP"))
        $ip = getenv("HTTP_CLIENT_IP");
    else if (getenv("HTTP_X_FORWARDED_FOR"))
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    else if (getenv("REMOTE_ADDR"))
        $ip = getenv("REMOTE_ADDR");
    else $ip = "Unknow";
    return $ip;
}

//初始化数据
function MakeRuntimeInfo()
{
    return [
        '_BaseInfo' => array([
            "URL" => GetProtocol() . $_SERVER['HTTP_HOST'] . ':' . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"],
            "IP" => GetIP(),
            "DateTime" => date('Y-m-d H:i:s', time()),
            "Time" => GetNowTime(),
            "IsDanger" => false,
        ])[0],
        '_WafInfo' => array($GLOBALS['Waf_PHPIFNO'])[0],
        '_GET' => array($GLOBALS['_GET'])[0],
        '_POST' => array($GLOBALS['_POST'])[0],
        '_COOKIE' => array($GLOBALS['_COOKIE'])[0],
        '_ENV' => array($GLOBALS['_ENV'])[0],
        '_REQUEST' => array($GLOBALS['_REQUEST'])[0],
        '_SERVER' => array($GLOBALS['_SERVER'])[0],
        '_FILES' => array($GLOBALS['_FILES'])[0],
        '_FilterAction' => [],
        '_CallStack' => []
    ];
}
//保存日志
function WafSaveLog($waf_gobal_name)
{
    $wafInfo = $GLOBALS['Waf_PHPIFNO'];
    $logFilePath = "";
    if ($wafInfo) {
        $logFilePath = $wafInfo["protectDataPath"] . 'logs/' . $GLOBALS[$waf_gobal_name]["_BaseInfo"]["Time"] . ".log";
    }
    //保存日志
    if ($logFilePath != "") {
        if (!is_dir(dirname($logFilePath))) {
            mkdir(dirname($logFilePath), 0777);
        }
        file_put_contents($logFilePath, json_encode($GLOBALS[$waf_gobal_name], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}

//防火墙处理
function WafInit($waf_toekn)
{
    $waf_gobal_name = "CellWaf_" . md5($waf_toekn);
    //数据处理保持部分
    if (!array_key_exists($waf_gobal_name, $GLOBALS)) {
        //初始化数据
        $GLOBALS[$waf_gobal_name] = MakeRuntimeInfo();
        header("X-Protect-By: CellWaf By MoeGuo H.");
    }
    //日志文件夹路径
    $logFilePath = "";
    $wafInfo = $GLOBALS['Waf_PHPIFNO'];
    if ($wafInfo) {
        //添加程序堆栈信息
        array_push($GLOBALS[$waf_gobal_name]["_CallStack"], $wafInfo["filePath"]);
        $logFilePath = $wafInfo["protectDataPath"] . 'logs/';
    }
    //管理监控部分
    if (array_key_exists("WafAction", $_REQUEST) && $_REQUEST["waf_toekn"] === $waf_toekn) {
        header('Content-Type:application/json; charset=utf-8');
        if ($_REQUEST["WafAction"] === "LastLogTime") {
            $files = scandir($logFilePath);
            if (!$files) {
                exit(json_encode(["LastLogTime" => "0"]));
            }
            $filenames = [];
            foreach ($files as $key => $value) {
                $name = trim(pathinfo($value, PATHINFO_FILENAME));
                if (strlen($name) > 2) {
                    array_push($filenames, $name);
                }
            }
            rsort($filenames);
            if (!$filenames[0]) {
                $filenames[0] = "0";
            }
            exit(json_encode(["LastLogTime" => "$filenames[0]"]));
        } else if ($_REQUEST["WafAction"] === "GetLogFiles" && array_key_exists("AfterTime", $_REQUEST)) {

            if (!array_key_exists("BeforeTime", $_REQUEST) && $_REQUEST["BeforeTime"]) {
                $_REQUEST["BeforeTime"] = GetNowTime();
            }
            $files = scandir($logFilePath);
            if (!$files) {
                exit(json_encode(["Files" => []]));
            }
            $filenames = [];
            foreach ($files as $key => $value) {
                $name = trim(pathinfo($value, PATHINFO_FILENAME));
                if (strlen($name) > 2 && $_REQUEST["AfterTime"] < $name && $_REQUEST["BeforeTime"] >= $name) {
                    array_push($filenames, ["fileName" => "$value", "fileData" => base64_encode(file_get_contents($logFilePath . "/" . $value))]);
                }
            }
            exit(json_encode(["Files" => $filenames]));
        } else {
            exit(json_encode(["State" => "NotFound"]));
        }
    }
    
}
