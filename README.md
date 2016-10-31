#LFI（Local File Include）漏洞
###漏洞简介

下面是纯bb，了解过的跳过这部分；

解释：能够打开并包含`本地`文件的漏洞；

这里区别一下RFI，远程文件包含漏洞；

意义：文件包含漏洞是"代码注入"的一种，包含即执行，可干的坏事可想而知，看i春秋总结的危害有如下几种：

1. PHP包含漏洞结合上传漏洞；
2. PHP包含读文件；
3. PHP包含写文件；
4. PHP包含日志文件；
5. PHP截断包含；
6. PHP内置伪协议利用。

PHP中文件包含函数有以下四种：

1. require
2. require_once
3. include
4. include_once

include和require区别主要是，include在包含的过程中如果出现错误，会抛出一个警告，程序继续正常运行；而require函数出现错误的时候，会直接报错并退出程序的执行。而include\_once(),require_once()这两个函数，与前两个的不同之处在于
这两个函数只包含一次，适用于在脚本执行期间同一个文件有可能被包括超过一次的情况下，你想确保它只被包括一次以避免函数重定义，变量重新赋值等问题。

当使用这4个函数包含一个新的文件时，该文件将作为PHP代码执行，PHP的内核并不会在意被包含的文件是什么类型。即你可以上传一个含shell的txt或jpg文件，包含它会被当作PHP代码执行（图马）。

###这个玩意儿与CTF的渊源
1. php://伪协议 >> 访问各个输入/输出流；
	- php://filter 
		- 解释：php://filter是一种元封装器，设计用于"数据流打开"时的"筛选过滤"应用，对本地磁盘文件进行读写。简单来讲就是可以在执行代码前将代码换个方式读取出来，只是`读取`，`不需要`开启allow_url_include； 
		- 用法：?file=php://filter/convert.base64-encode/resource=xxx.php
		- ?file=php://filter/read=convert.base64-encode/resource=xxx.php 一样
		- 例子：
			- [http://4.chinalover.sinaapp.com/web7/index.php](http://4.chinalover.sinaapp.com/web7/index.php)
			- nctf{edulcni_elif_lacol_si_siht}
			- 本地：filter文件夹
	- php://input 
		- 解释：上面filter既然能读文件，肯定还能写文件，这就可以利用input将数据POST过去，即php://input是用来接收post数据的；
		- 用法：?file=php://input  数据POST过去
		- 注意：
			- 如果php.ini里的allow\_url_include=On（PHP < 5.30！）,就可以造成任意代码执行，即POST过去一句话，如<?php phpinfo();?>，即可执行；
		- 例子：
			- 碰到file\_get_contents()就要想到用php://input绕过，具体函数意义下一项；
			- [http://ctf4.shiyanbar.com/web/9](http://ctf4.shiyanbar.com/web/9)
			- 并且可以用data伪协议来绕过；
			- 由于这个题由于存在extract()函数，存在变量覆盖漏洞；直接?flag=1&shiyan=即可
			- 本地：input文件夹
			- 2016华山杯有一道，本地data文件夹，可以利用data流；
2. data://伪协议 >> 数据流封装器，和php://相似都是利用了流的概念，将原本的include的文件流重定向到了用户可控制的输入流中，简单来说就是执行文件的包含方法包含了你的输入流，通过你输入payload来实现目的；
	- data://text/plain 
		- 解释：
		- 用法：?file=data://text/plain;base64,base64编码的payload
		- 注意：
			- `<?php phpinfo();`,这类执行代码最后没有?>闭合；
			- 如果php.ini里的allow\_url_include=On（PHP < 5.30！）,就可以造成任意代码执行；
		- 例子： 
			- 和php伪协议的input类似，碰到file\_get_contents()来用；
			- 本地：data文件夹
3. phar://伪协议 >> 数据流包装器，自 PHP 5.3.0 起开始有效，正好契合上面两个伪协议的利用条件。说通俗点就是php解压缩包的一个函数，解压的压缩包与后缀无关。
	- phar://
		- 用法：?file=phar://压缩包/内部文件
		- 注意：
			- PHP版本需大于等于 5.3；
			- 压缩包一般是phar后缀，需要代码来生成，但是zip后缀也可以；
			- 压缩包需要是zip协议压缩，rar不行，tar等格式待测；
			- 利用url的压缩包后缀可以是任意后缀；
		- 例子：
			- 本地：phar1文件（SWPU2016，限制上传类型）
			- 本地：phar2文件（限制上传类型，上传重命名）

###函数解释

1. file\_get_contents()：这个函数就是把一个文件里面的东西 （字符）全部return出来。可以放一个变量里面，也就是字符串变量了，也可以字符串直接echo。相当于fopen,fread,fclose的组合。
2. include()：（就是require,reqiuire_once,include_require这一类）include是针对文档的代码结构的。也就是说，include进来，成了这个文件的其中一部分源代码。
3. include把导入的字符串当成当前文件的代码结构，而file_get_contents只是返回字符串。这是两个最大的不同。关于字符串执行的问题，file_get_contents返回的字符串失去了被执行的能力，哪怕字符串里面有<?php ?>，一样能拿出来但不执行。而include导入的字符串，如果被导入的文件有<?php，那就成为php代码的一部分。如果没有<?php，只是把它当做源文件<?php ?>外的一部分。

###参考博文：
1. [http://www.cnblogs.com/LittleHann/p/3665062.html](http://www.cnblogs.com/LittleHann/p/3665062.html)
2. [http://www.cnblogs.com/iamstudy/articles/include_file.html](http://www.cnblogs.com/iamstudy/articles/include_file.html)
3. [http://mp.weixin.qq.com/s?__biz=MzAwMTUyMjQ5OA==&mid=2650963079&idx=1&sn=cf0e9c60a68ea7e272e8ad77e6816ebe&scene=1&srcid=0824QF8DtX5jg5FSnZlQlLHR#rd](http://mp.weixin.qq.com/s?__biz=MzAwMTUyMjQ5OA==&mid=2650963079&idx=1&sn=cf0e9c60a68ea7e272e8ad77e6816ebe&scene=1&srcid=0824QF8DtX5jg5FSnZlQlLHR#rd)
4. [http://www.91ri.org/13363.html](http://www.91ri.org/13363.html)