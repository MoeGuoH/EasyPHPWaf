#!/bin/bash
#./protect.sh / thinkShop reload
#./protect.sh /app thinkShop setwaf
#Config
TargePath=$1
TargeName=$2
ProtectDataPath="/tmp/protect/${TargeName}/"
ProtectWaf="$(pwd)/waf.php"

function genWafCode(){
    filePath=$1
    raw_code=$(cat ${filePath})
    namespace=$(cat ${filePath} | grep "^namespace.*;")
    PHPWafCode="<?php 
    ${namespace}
    try {
        \$GLOBALS[\"Waf_PHPIFNO\"]=[\"protectDataPath\"=>\"${ProtectDataPath}\",\"TargeName\"=>\"${TargeName}\",\"filePath\"=>\"${filePath}\"];
        require(\"${ProtectWaf}\");
    } catch (Exception \$e) {
        echo \$e->getMessage();
    }
?>"
    echo "${PHPWafCode}" > ${filePath}
    echo "${raw_code}" >> ${filePath}
}

function backup(){
    #Init
    mkdir -p ${ProtectDataPath}
    #SaveMysql
    mysqldump -u root -p --all-databases > ${ProtectDataPath}/db.sql
    #BackUpWeb
    tar -zcf ${ProtectDataPath}/wwwroot.tar.gz ${TargePath}/*
}

function reload(){
    #ReloadFile
    tar -zxf ${ProtectDataPath}/wwwroot.tar.gz -C ${TargePath}/
    #ReloadMysql
    mysql -u root -p < ${ProtectDataPath}/db.sql
}

function setwaf(){
    chmod 777 -R ${ProtectDataPath}
    echo -e "Protect File" > ${ProtectDataPath}/Protect.log
    for line in $(find ${TargePath}/* -type f -name '*.php'); do
        echo -e "$line" >> ${ProtectDataPath}/Protect.log
        genWafCode $line
    done
}

case "$3" in

    backup )
    echo "开始备份"
    backup
    echo "备份结束"
    ;;

    reload )
    echo "开始恢复"
    reload
    echo "恢复结束"
    ;;

    setwaf )
    echo "开始配置Waf"
    setwaf
    echo "配置Waf结束"
    ;;

    * )
    echo "$0.sh filepath hostname [backup|reload|setwaf] "
    ;;

esac