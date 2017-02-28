import re
import time
import urllib2
import sys
import os
from bs4 import BeautifulSoup


class parseHtml:
    filename = ''
    filestr = ''
    filesoup = ''

    def __init__(self, param):
        if (os.path.isfile(param) is False):
            print "[ERR]Not Found File" + param
            sys.exit(1)
        self.filename = param
        self.filestr = open(self.filename, 'r')
        self.filesoup = BeautifulSoup(self.filestr)

    def getRecordTitleAndName(self):

        title = self.filesoup.h2.get_text()
        titlename = title.strip()
        webchat_name = self.filesoup.find(id='post-user').get_text()
        return [titlename, webchat_name]

    def getWechatNumberAndName(self):

        name = self.filesoup.find("strong", {"class": "profile_nickname"}).string.strip()
        number = self.filesoup.find("p", {"class": "profile_account"}).get_text().split()
        return [name, number[1]]

    def getRecordList(self):
        for x in self.filesoup.find_all('script'):
            item = x.get_text()
            if re.search('{.*list.*}', item) is not None:
                data_json_str = '{' + re.search("{.*list.*}", item).group(0) + '}'
                temp = data_json_str.replace('&nbsp;', ' ').replace('&amp;', '&')
                return data_json_str[1:-1].encode("utf-8")
        return False

    def getNumberSourceUrl(self):
        if (self.filesoup.find(uigs='account_name_0') is None):
            return False
        account = self.filesoup.find(uigs='account_name_0').get('href')
        return account

    def getRecordContent(self):
        str_data = '['
        millis = int(round(time.time() * 1000))
        if (self.filesoup.find(id='js_content') is None):
            sys.exit(1)
        for item in self.filesoup.find(id='js_content').find_all('p'):
            if (re.search('Tag', str(type(item))) is None):
                continue
            item_string = ''
            for string in item.strings:
                item_string += string
            a_id = str(millis)
            item_string = item_string.strip()
            if (item.img is not None and len(item_string) > 0):
                for child in item.find_all('img'):
                    pic = child.get('data-src')
                    str_data += '{"a_id":"' + a_id + '","detail_type":'
                    str_data += '"image",'
                    str_data += '"value":"' + pic + '"'
                    str_data += ',"note":"' + item_string + '"'
                    str_data += ',"title":"\u56fe\u7247"},'+"\n"
                    item_string = ''
                    millis = millis + 1
            elif (item.iframe is not None and len(item_string) > 0):
                for child in item.find_all('iframe'):
                    video = item.iframe.get('data-src')
                    if (re.search('http', video) is None):
                        continue
                    video_http = video.replace('https', 'http')
                    video_player = video_http.replace('preview', 'player')
                    video_split = video_player.split("?")
                    video_set = video_split[1].split("&")
                    for video_set_item in video_set:
                        if(re.match('vid', video_set_item) is not None):
                            video = video_split[0] + '?' + video_set_item + '&tiny=0&auto=0'
                            str_data += '{"a_id":"' + a_id + '","detail_type":'
                            str_data += '"video",'
                            str_data += '"value":"' + video + '"'
                            str_data += ',"title":"\u89c6\u9891"},'+"\n"
                            millis = millis + 1
                str_data += '{"a_id":"' + a_id + '","detail_type":'
                str_data += '"para",'
                str_data += '"value":"' + item_string + '"'
                str_data += ',"title":"\u6bb5\u843d"},' + "\n"
                millis = millis + 1
            elif (len(item_string) > 0):
                if (item.find("strong") is None):
                    str_data += '{"a_id":"' + a_id + '","detail_type":'
                    str_data += '"para",'
                    str_data += '"value":"' + item_string + '"'
                    str_data += ',"title":"\u6bb5\u843d"},'+"\n"
                else:
                    is_sub_title = len(item_string) - len(item.strong.get_text())
                    if (is_sub_title > 0):
                        str_data += '{"a_id":"' + a_id + '","detail_type":'
                        str_data += '"para",'
                        str_data += '"value":"' + item_string + '"'
                        str_data += ',"title":"\u6bb5\u843d"},'+"\n"
                    else:
                        str_data += '{"a_id":"' + a_id + '","detail_type":'
                        str_data += '"sub_title",'
                        str_data += '"value":"' + item_string + '"'
                        str_data += ',"title":"\u5c0f\u6807\u9898"},'+"\n"
            elif (item.img is not None):
                for child in item.find_all('img'):
                    pic = child.get('data-src')
                    str_data += '{"a_id":"' + a_id + '","detail_type":'
                    str_data += '"image",'
                    str_data += '"value":"' + pic + '"'
                    str_data += ',"title":"\u56fe\u7247"},'+"\n"
                    millis = millis + 1
            elif (item.iframe is not None):
                video = item.iframe.get('data-src')
                if (re.search('http', video) is None):
                    continue
                video_http = video.replace('https', 'http')
                video_player = video_http.replace('preview', 'player')
                video_split = video_player.split("?")
                video_set = video_split[1].split("&")
                for video_set_item in video_set:
                    if(re.match('vid', video_set_item) is not None):
                        video = video_split[0] + '?' + video_set_item + '&tiny=0&auto=0'
                        str_data += '{"a_id":"' + a_id + '","detail_type":'
                        str_data += '"video",'
                        str_data += '"value":"' + video + '"'
                        str_data += ',"title":"\u89c6\u9891"},' + "\n"
                        millis = millis + 1
            millis = millis + 1
        str_data = str_data[:-2]
        str_data += ']'
        return str_data


request_type = sys.argv[1]
request_param = sys.argv[2]

if (request_type is None or request_param is None):
    print "params is illegal.[request_type]" + request_type + "[request_param]" + request_param
    sys.exit(1)
if (request_type == '1'):
    request = parseHtml(request_param)
    url = request.getNumberSourceUrl()
    if (url is False):
        print "[ERR][step1]file is not available " + request_param
        sys.exit(1)
    else:
        print url.encode("utf-8")
        sys.exit(0)
elif (request_type == '2'):
    request = parseHtml(request_param)
    record_list = request.getRecordList()
    numberName = request.getWechatNumberAndName()
    if (record_list is False):
        print "[ERR][step2]file is not available " + request_param
    else:
        print numberName[0].encode("utf-8")
        print numberName[1].encode("utf-8")
        print record_list
        sys.exit(0)
elif (request_type == '3'):
    request = parseHtml(request_param)
    titleAndName = request.getRecordTitleAndName()
    record_content = request.getRecordContent()
    if (record_content is False):
        print "[ERR][step3]file is not available " + request_param
        sys.exit(1)
    else:
        print titleAndName[0].encode("utf-8")
        print titleAndName[1].encode("utf-8")
        print record_content.encode("utf-8")
        sys.exit(0)
