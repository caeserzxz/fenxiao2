'use strict';

+function ($, Vue) {

    $.api = {

        request: function (url, data, options) {
            options = $.extend(options, {
                method: 'POST',
                url: url,
                data: data,
                dataType: 'json',
            });

            return $.ajax(options).then(function (res, status, deferred) {

                if (typeof res !== 'object') {
                    res = {
                        status: 'error',
                        message: '未知错误',
                    };
                }

                var actionStatus = res.status,
                    token = deferred.getResponseHeader('__token__');

                if (actionStatus === 'error') {
                    var message = res.message || '未知错误';

                    return $.Deferred().reject(message, token, deferred);

                } else if (actionStatus === 'needLogin') {

                    $.toast('请重新登录', 'cancel', function () {
                        var loginURL = new URI(res.data && res.data.loginURL);

                        if (!res.data.noDirect) {
                            // 记录当前路径
                            loginURL.search({
                                redirectURL: location.href,
                            });
                        }
                        location.assign(loginURL);
                    });
                    return $.Deferred();
                }

                return $.Deferred().resolve(res.data, token, deferred);

            }, function (deferred, status, message) {
                console.error(message);

                return $.Deferred().reject('未知错误', deferred);
            });
        },
    };

    Vue.mixin({
        filters: {

            /**
             * 转换分单位价格到元单位
             *
             * @param value 价值（单位分
             * @returns {Number} 价值（单位元，两位小数
             */
            amountText: function(value) {
                value = parseInt(value, 10) || 0;
                return value / 100;
            },

            rmbPrefix: function(amount) {
                if (!amount) {
                    amount = 0;
                }
                return '￥' + amount;
            },

            rmbSuffix: function(amount) {
                if (!amount) {
                    amount = 0;
                }
                return amount + '元';
            },
        },

        methods: {

            /**
             * 通用异常处理
             *
             * @param {String} msg 错误提示
             * @param {String} token 请求令牌
             */
            failHandler: function (msg, token) {
                // 更新令牌
                if (token) {
                    this.token = token;
                }
                msg && $.alert(msg);
            },
        }
    });

}(jQuery, Vue);