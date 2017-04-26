# LFI（Local File Include）漏洞

## 漏洞简介

下面是纯bb，了解过的跳过这部分；

解释：LFI是能够打开并包含`本地`文件的漏洞；

这里区别一下RFI，远程文件包含漏洞；

意义：文件包含漏洞是"代码注入"的一种，包含即执行，可干的坏事可想而知，看i春秋总结的危害有如下几种：

1. PHP包含漏洞结合上传漏洞；
2. PHP包含读文件；
3. PHP包含写文件；
4. PHP包含日志文件；
5. PHP截断包含；
6. PHP内置伪协议利用。

PHP中文件包含函数有以下四种：

1. require()
2. require_once()
3. include()
4. include_once()

include和require区别主要是，include在包含的过程中如果出现错误，会抛出一个警告，程序继续正常运行；而require函数出现错误的时候，会直接报错并退出程序的执行。

而include\_once()，require_once()这两个函数，与前两个的不同之处在于这两个函数只包含一次，适用于在脚本执行期间同一个文件有可能被包括超过一次的情况下，你想确保它只被包括一次以避免函数重定义，变量重新赋值等问题。

最简单的漏洞代码：`<?php include($_GET[file]);?>`

当使用这4个函数包含一个新的文件时，该文件将作为PHP代码执行，PHP的内核并不会在意被包含的文件是什么类型。即你可以上传一个含shell的txt或jpg文件，包含它会被当作PHP代码执行（图马）。

## 这个玩意儿与CTF的渊源（协议基础）
1. `php://`伪协议 >> 访问各个输入/输出流；
   - php://filter 
     1. 解释：php://filter是一种元封装器，设计用于"数据流打开"时的"筛选过滤"应用，对本地磁盘文件进行读写。简单来讲就是可以在执行代码前将代码换个方式读取出来，只是读取，`不需要`开启allow\_url_include； 

     2. 用法：?file=php://filter/convert.base64-encode/resource=xxx.php

     3. ?file=php://filter/read=convert.base64-encode/resource=xxx.php 一样

     4. 例子：

        - http://4.chinalover.sinaapp.com/web7/index.php](http://4.chinalover.sinaapp.com/web7/index.php)
        - nctf{edulcni_elif_lacol_si_siht}
        - 练习题目源码文件见filter文件夹；

   - php://input 

     1. 解释：上面filter既然能读文件，肯定还能写文件，这就可以利用input将数据POST过去，即php://input是用来接收post数据的；
     2. 用法：?file=php://input  数据利用POST传过去
     3. 注意：如果php.ini里的allow\_url_include=On（PHP < 5.30）,就可以造成任意代码执行，在这可以理解成远程文件包含漏洞（RFI），即POST过去一句话，如<?php phpinfo();?>，即可执行；
     4. 例子：
        - 碰到file\_get_contents()就要想到用php://input绕过，因为php伪协议也是可以利用http协议的，即可以使用POST方式传数据，具体函数意义下一项；
        - http://ctf4.shiyanbar.com/web/9](http://ctf4.shiyanbar.com/web/9)
        - 并且可以用data伪协议来绕过，由于这个题由于存在extract()函数，存在变量覆盖漏洞；直接?flag=1&shiyan=即可；
        - 练习题目源码文件见input1文件夹；
        - 2016华山杯有一道题，源码见本地data文件夹，这个可以利用data流；
2. `data://`伪协议 >> 数据流封装器，和php://相似都是利用了流的概念，将原本的include的文件流重定向到了用户可控制的输入流中，简单来说就是执行文件的包含方法包含了你的输入流，通过你输入payload来实现目的；
   - data://text/plain 

     1. 解释：
     2. 用法：?file=data://text/plain;base64,base64编码的payload

     3. 注意：

        - `<?php phpinfo();`,这类执行代码最后没有?>闭合；

        - 如果php.ini里的allow\_url_include=On（PHP < 5.30）,就可以造成任意代码执行，同理在这就可以理解成远程文件包含漏洞（RFI）；
     4. 例子： 
        - 和php伪协议的input类似，碰到file\_get_contents()来用；
        - 练习题目源码文件见data文件夹；

3. `phar://`伪协议 >> 数据流包装器，自 PHP 5.3.0 起开始有效，正好契合上面两个伪协议的利用条件。说通俗点就是php解压缩包的一个函数，解压的压缩包与后缀无关。
   - phar://
     1. 用法：?file=phar://压缩包/内部文件
     2. 注意：

        - PHP版本需大于等于 5.3，这就说明上述协议已经挂掉了，但又出来了phar协议前赴后继；
        - 压缩包一般是phar后缀，需要代码来生成，但是zip后缀也可以；
        - 压缩包需要是zip协议压缩，rar不行，tar等格式待测；
        - 利用url的压缩包后缀可以是任意后缀；
     3. 例子：
        - 本地：phar1文件（SWPU2016，限制上传类型）
        - 本地：phar2文件（限制上传类型，上传重命名）

4. 上述说的php.ini文件的限制如下：
   - allow\_url_fopen = On `默认打开` ，允许URLs作为像files一样作为打开的对象；
   - allow\_url_include = On `默认关闭` ，允许include/require函数像打开文件一样打开URLs；

## 函数解释

1. file\_get_contents()：这个函数就是把一个文件里面的东西 （字符）全部return出来作为字符串。
   - 除此之外，通过实践我发现这个函数如果直接把字符串当作参数会报错，但如果包含的是http协议的网址，则会像curl命令一样，把源码读出来。而php伪协议也是识别http协议的，所以说上面php://input可以将POST的数据读过来来赋值给参数，这就造成了上述那个例子的漏洞。
2. include()：（就是require,reqiuire_once,include_require这一类）include是针对文档的代码结构的。也就是说，include进来，成了这个文件的其中一部分源代码，这类函数就是文件包含漏洞的罪魁祸首。
3. include把导入的字符串当成当前文件的代码结构，而file_get_contents只是返回字符串，这是两个函数最大的不同。关于字符串执行的问题，file_get_contents返回的字符串失去了被执行的能力，哪怕字符串里面有<?php ?>，一样能拿出来但不执行。而include导入的字符串，如果被导入的文件有<?php，那就成为php代码的一部分。如果没有<?php，只是把它当做源文件<?php ?>外的一部分。

## 简单有趣的Web题

1. 本地包含、代码注入：
   - 源码文件见：命令执行文件夹
   - payload：http://localhost/?hello=);echo \`cat flag.php\`;//
   - );用来闭合var_dump()方法，echo\`command\`;执行命令，//注释。
2. php://input伪协议：
   - 源码文件见input2文件夹
   - payload：http://localhost/index.php?path=php://input POST数据<?php echo \`cat flag.php\`;?>
   - php://input协议读取POST过来的数据并执行。

## Tips

1. 上述filter伪协议利用的是encode编码为base64再带出来，filter还有decode解密语句，可利用场景如下（文字摘自phithon博客）：

   - 源码存在eval(xxx)，但xxx长度限制为16个字符，而且不能用eval或assert，怎么执行命令。题目源码文件见tips.php，那么利用这个代码怎么拿到webshell？

   - 利用file_put_contents可以将字符一个个地写入一个文件中，大概请求如下：

   - file_put_contents的第一个参数是文件名，我传入N。PHP会认为N是一个常量，但我之前并没有定义这个常量，于是PHP就会把它转换成字符串'N'；第二个参数是要写入的数据，a也被转换成字符串'a'；第三个参数是flag，当flag=8的时候内容会追加在文件末尾，而不是覆盖。

   - 除了file_put_contents，error_log函数效果也类似。

   - 但这个方法有个问题，就是file_put_contents第二个参数如果是符号，就会导致PHP出错，比如`param=$_GET[a](N,<,8);&a=file_put_contents`。但如果要写webshell的话，“<”等符号又是必不可少的。

   - 于是微博上 @买贴膜的 想出一个办法，每次向文件'N'中写入一个字母或数字，最后构成一个base64字符串，再包含的时候使用php://filter对base64进行解码即可。

   - 这时候就利用了decode了，成功getshell。

   - ```php+HTML
     # 每次写入一个字符：PD9waHAgZXZhbCgkX1BPU1RbOV0pOw
     # 最后包含
     param=include$_GET[0];&0=php://filter/read=convert.base64-decode/resource=N
     ```

## 参考博文

1. [http://www.cnblogs.com/LittleHann/p/3665062.html](http://www.cnblogs.com/LittleHann/p/3665062.html)
2. [http://www.cnblogs.com/iamstudy/articles/include_file.html](http://www.cnblogs.com/iamstudy/articles/include_file.html)
3. [http://mp.weixin.qq.com/s?__biz=MzAwMTUyMjQ5OA==&mid=2650963079&idx=1&sn=cf0e9c60a68ea7e272e8ad77e6816ebe&scene=1&srcid=0824QF8DtX5jg5FSnZlQlLHR#rd](http://mp.weixin.qq.com/s?__biz=MzAwMTUyMjQ5OA==&mid=2650963079&idx=1&sn=cf0e9c60a68ea7e272e8ad77e6816ebe&scene=1&srcid=0824QF8DtX5jg5FSnZlQlLHR#rd)
4. [http://www.91ri.org/13363.html](http://www.91ri.org/13363.html)
5. [http://www.admintony.top/?p=1172](http://www.admintony.top/?p=1172)
6. https://www.leavesongs.com/PHP/bypass-eval-length-restrict.html