#!/usr/bin/python3
from requests import request
from base64 import b64decode
import os
import sys
from pathlib import Path
from json import load as json_load, dumps as json_dumps
import time
from sys import argv
from pygments import highlight, lexers, formatters


class cellwaf():
    def __init__(self, pathName, targetUrl, token):
        self._token = token
        self._targetUrl = targetUrl+"?waf_toekn="+self._token
        self.SelfLastLogTime = 0
        self.ServerLastLogTime = 0
        self.logsPath = os.path.abspath(pathName)
        # 初始化文件夹
        if (os.path.exists(self.logsPath) == False):
            os.makedirs(self.logsPath)
            pass
        pass

    def _UpdateLastLogTime(self):
        result = request("GET", self._targetUrl +
                         "&WafAction=LastLogTime").json()
        if result["LastLogTime"]:
            self.ServerLastLogTime = float(result["LastLogTime"])

    def _JsonShowFormart(self, obj):
        result = json_dumps(obj, sort_keys=True, indent=4)
        result = highlight(result, lexers.JsonLexer(),
                           formatters.TerminalFormatter())
        return result

    def _PrintLogToScreen(self, wafLogInfo):
        if wafLogInfo:
            colorStr = "\033[32m[Safe] "
            White = "\033[37m"
            if len(wafLogInfo["_FilterAction"]) > 0:
                colorStr = "\033[35m[Warming] "
            if wafLogInfo["_BaseInfo"]["IsDanger"]:
                colorStr = "\033[31m[Danger] "
            print("\033[0;37m"+"-"*50)
            print(colorStr+"Host: %s Time:%s %s IP: %s\nMethod:%s FilePath: %s\nURL: %s\n%sGET: %s\nPost: %s\nCookie: %s" % (
                wafLogInfo["_WafInfo"]["TargeName"],
                wafLogInfo["_BaseInfo"]["DateTime"],
                wafLogInfo["_BaseInfo"]["Time"],
                wafLogInfo["_BaseInfo"]["IP"],
                wafLogInfo["_SERVER"]["REQUEST_METHOD"],
                wafLogInfo["_SERVER"]["SCRIPT_FILENAME"],
                wafLogInfo["_BaseInfo"]["URL"],
                White,
                self._JsonShowFormart(wafLogInfo["_GET"]),
                self._JsonShowFormart(wafLogInfo["_POST"]),
                self._JsonShowFormart(wafLogInfo["_COOKIE"]),
            ))
            if len(wafLogInfo["_FilterAction"]) > 0:
                print("FilterAction:%s" %
                      self._JsonShowFormart(
                          wafLogInfo["_FilterAction"]
                      ))
        pass

    def _UpdateLocalLogTime(self):
        filenames = os.listdir(self.logsPath)
        filenames.sort()
        print("\033[1;33m"+"-"*100)
        for file in filenames:
            if not os.path.isdir(file):
                time = float(Path(file).stem)
                if(time > self.SelfLastLogTime):
                    self.SelfLastLogTime = time
                    if os.path.getsize(self.logsPath+"/"+file) > 0:
                        with open(self.logsPath+"/"+file, "r", encoding="UTF-8") as f:
                            self._PrintLogToScreen(json_load(f))
                    else:
                        print("\033[0;37m"+"-"*50)
                        print("[]")
        print("\033[1;33m"+"-"*100)

        pass

    def UpdateLogFiles(self):
        self._UpdateLastLogTime()
        if(self.SelfLastLogTime < self.ServerLastLogTime):
            result = request("POST", self._targetUrl +
                             "&WafAction=GetLogFiles", data={
                                 "AfterTime": self.SelfLastLogTime,
                                 "BeforeTime": self.ServerLastLogTime,
                             }).json()
            for fileData in result["Files"]:
                with open(self.logsPath+"/"+fileData["fileName"], "wb+") as f:
                    f.write(b64decode(fileData["fileData"]))
                    pass
                pass
            pass
            self._UpdateLocalLogTime()
        pass


if __name__ == "__main__":

    if len(argv) < 4:
        print(
            "Usage:%s Gamebox1 http://172.17.0.2/ hRwNxiAPJaJIJUHUKbN3Q5awoMn90WQ6" % (argv[0]))
        sys.exit()
    cw = cellwaf(argv[1], argv[2], argv[3])
    while True:
        cw.UpdateLogFiles()
        time.sleep(2)
    pass
