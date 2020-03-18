<?php
/*
 * Project: CellWaf
 * File: EnterModule
 * Desc: 防火墙入口文件
 * Author: MoeGuo H. http://www.avi.moe
 * DateTime: 2019.11.14
 */
require(dirname(__FILE__) . "/waf_config.php");
require_once(dirname(__FILE__) . "/lib.php");
require_once(dirname(__FILE__) . "/filter.php");
//初始化Waf
WafInit($_WafConfig_Token);
//过滤
WafFilter($_WafConfig_Token);
//保存日志
WafSaveLog($_WafConfig_GobalName);
