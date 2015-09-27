define("blog", ['jquery', 'showdown', 'hljs', 'infintescroll'], function ($, showdown, hljs, infintescroll) {
    'use strict';
    var exports = {
        //记录博客总页数，用于翻页
        blogTotalPages: 1,
        //根据页码生成ajax请求链接
        pageUrl: function (page) {
            var tag = $('#tag').val() ||  '';
            return "/api/page=" + page + "/tag=" + tag + "/blog";
        },
        //ajax回调绘制列表页面
        buildListPage: function (data) {
            exports.blogTotalPages = data.records.total_pages;
            var i = 0,
                html = '',
                length = data.records.items.length,
                tags = '';
            for (i = 0; i < length; i++) {
                html += '<div class="post-preview"><a href="/article/info/' +
                    data.records.items[i].id + '"><h2 class="post-title">' +
                    data.records.items[i].title + '</h2></a><h4  class="post-subtitle">';
                if (data.records.items[i].tags.length > 0) {
                    tags = data.records.items[i].tags.split(',');
                    $.each(tags, function (i, tag) {
                        html += '<span  class="' + exports.calClass(tag) + '">' + tag + '</span> ';
                    });
                }
                html += '</h4><p class="post-meta">发布于' + data.records.items[i].updated_at + '</p></div><hr>';
            }
            $('._bloglist').append(html);
        },
        //无限翻页控件
        infiniteScrollList: function () {
            $('._bloglist').infinitescroll({
                loading: {
                    finished: undefined,
                    finishedMsg: "<em>已经到达最后一页，无更多内容。</em>",
                    //img: null,
                    //msg: null,
                    msgText: "<em>载入下一页中s...</em>",
                    selector: null,
                    speed: 'fast',
                    start: undefined
                },
                //behavior: '/api/page=1/blog',
                nextSelector: "._nav a:first",
                navSelector: "._nav",
                itemSelector: ".post",
                //animate: true,
                dataType: 'json',
                appendCallback: false,
                //bufferPx: 40,
                //errorCallback: function () {
                //},
                infid: undefined, //Instance ID
                path: exports.pageUrl, // Can either be an array of URL parts
                //(e.g. ["/page/", "/"]) or a function that accepts the page number and returns a URL
                maxPage: exports.blogTotalPages || 1// to manually control maximum page (when maxPage is undefined, maximum page limitation is not work)
            }, function (data) {
                exports.buildListPage(data);
            });
        },
        //ajax请求博客列表首页
        blogList: function (page, tag) {
            page = page || 1;
            tag = tag || '';
            $.ajax({
                url: '/api/page=' + page + '/tag=' + tag + '/blog',
                type: 'get',
                dataType: 'json',
                cache: false,
                success: function (data) {
                    exports.buildListPage(data);
                    exports.infiniteScrollList();
                }
            });
        },
        //博客详情页面
        blogInfo: function (id) {
            id = id || 1;
            $.ajax({
                url: '/api/id=' + id + '/blog',
                type: 'get',
                dataType: 'json',
                cache: false,
                success: function (data) {
                    var blog = data.records,
                        htmlTail = '<ul class="pager"><li class="next"><a href="/"> &larr;返回</a></li></ul>',
                        html = '<div class="row"><div class="col-md-6"> ',
                        editHtml = '<div class="col-md-6"><a class="btn btn-info pull-right btn-sm" href="/manager/edit/' + id + '">编辑</a></div>',
                        tags = blog.tags.split(','),
                        converter = new showdown.Converter();
                    $('title').html(blog.title+ ' - 赵枫杨的博客');
                    /* * * CONFIGURATION VARIABLES * * */
                    var disqus_shortname = 'souii';

                    /* * * DON'T EDIT BELOW THIS LINE * * */
                    (function() {
                        var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
                        dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
                        (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
                    })();
                    if (tags.length > 0 && tags[0] !== "") {
                        html += 'TAGS: ';
                    }
                    $.each(tags, function (i, tag) {
                        html += '<span  class="' + exports.calClass(tag) + '">' + tag + '</span> ';
                    });
                    html += '</div>';
                    if (exports.getRole('1')) {
                        html += editHtml;
                    }
                    converter.setFlavor('github');
                    $('._blogInfo').html(html + '</div></div><div class="markdown-body">' + converter.makeHtml(blog.content) + '</div>' + htmlTail);
                    $('pre code').each(function (i, block) {
                        hljs.highlightBlock(block);
                    });
                    $('._mainheading').html(blog.title);
                    $('._subheading').html('');
                    //使连接在新窗口中打开
                    $('.markdown-body a').each(function () {
                        $(this).attr('target', '_blank');
                    });
                }
            });
        },
        resetDisqus : function (newIdentifier, newUrl, newTitle, newLanguage) {
            DISQUS.reset({
                reload: true,
                config: function () {
                    this.page.identifier = newIdentifier;
                    this.page.url = newUrl;
                    this.page.title = newTitle;
                    this.language = newLanguage;
                }
            });
        },
        logout: function () {
            $.ajax({
                url: '/api/endsession',
                type: 'post',
                dataType: 'json',
                cache: false,
                success: function () {
                    debugger;
                    try{
                        if(QC.Login.check()){
                            QC.Login.signOut();
                        }
                    }catch (e ) {
                    }
                    window.location.href = window.location.origin;
                },
                error: function () {
                    window.location.href = window.location.origin;
                }
            });
        },
        tagClass: {
            0: "label label-default",
            1: "label label-primary",
            2: "label label-success",
            3: "label label-info",
            4: "label label-warning",
            5: "label label-danger"
        },
        calClass: function (tagName) {
            return exports.tagClass[tagName.length % 6];
        },
        getRole: function (needRole) {
            var ret = '',
                roleStr = $('meta[name=currentUser]').attr('role');
            needRole = needRole || '';
            if (needRole === '') {
                ret =  roleStr.split(',');
            } else {
                ret =  $.inArray(needRole, roleStr.split(',')) !== -1;
            }
            return ret;
        }
    };
    return exports;
});
