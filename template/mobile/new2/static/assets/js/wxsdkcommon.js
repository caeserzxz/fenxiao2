//文档加载完后立即执行
window.onload = function () {
    wx.config({
        debug: false, // 开启调试模式,调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
        appId: appId, // 必填，公众号的唯一标识
        timestamp: timestamp, // 必填，生成签名的时间戳
        nonceStr: nonceStr, // 必填，生成签名的随机串
        signature: signature,// 必填，签名，见附录1
        jsApiList: [
            "startRecord",
            "stopRecord",
            "onVoiceRecordEnd",
            "playVoice",
            "stopVoice",
            "onVoicePlayEnd",
            "uploadVoice",
            "downloadVoice",
            "translateVoice",
            "onMenuShareQQ",
            "onMenuShareWeibo",
            "onMenuShareTimeline",
            "onMenuShareAppMessage",
            "chooseImage",
            "previewImage",
            "uploadImage",
            "downloadImage",
            "getNetworkType",
            "openLocation",
            "getLocation",
            "hideOptionMenu",//隐藏右上角菜单接口
            "showOptionMenu",//显示右上角菜单接口
            "closeWindow",//显示右上角菜单接口
            "hideAllNonBaseMenuItem",//隐藏所有非基础按钮接口
            "showAllNonBaseMenuItem"//显示所有功能按钮接口
        ] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
    })

    wx.checkJsApi({
        jsApiList: [
            "startRecord",
            "stopRecord",
            "onVoiceRecordEnd",
            "playVoice",
            "stopVoice",
            "onVoicePlayEnd",
            "uploadVoice",
            "downloadVoice",
            "translateVoice",
            "onMenuShareQQ",
            "onMenuShareWeibo",
            "onMenuShareTimeline",
            "onMenuShareAppMessage",
            "chooseImage",
            "previewImage",
            "uploadImage",
            "downloadImage",
            "getNetworkType",
            "openLocation",
            "getLocation",
            "hideOptionMenu",//隐藏右上角菜单接口
            "showOptionMenu",//显示右上角菜单接口
            "closeWindow",//显示右上角菜单接口
            "hideAllNonBaseMenuItem",//隐藏所有非基础按钮接口
            "showAllNonBaseMenuItem"//显示所有功能按钮接口
        ], // 需要检测的JS接口列表，所有JS接口列表见附录2,
        success: function (res) {
            if (!res.checkResult.translateVoice || !res.checkResult.startRecord) {
                alert('你的微信客户端不支持JSSDK，请升级你的客户端');
            }
        }
    })


    //alert(action);

    if("undefined" != typeof action){
        if(action =="goodsInfo" ){ //|| action =="zpcollect_list"
            wx.ready(function () {

                var shareData = {
                    title: title, // 分享标题
                    desc: desc, // 分享描述
                    link: link, // 分享链接
                    imgUrl: imgUrl, // 分享图标
                    type: '', // 分享类型,music、video或link，不填默认为link
                    dataUrl: '', // 如果type是music或video，则要提供数据链接，默认为空
                    success: function () {
                        // 用户确认分享后执行的回调函数
                        //alert('已分享');
                        shareinfo(goods_id)
                    },
                    cancel: function () {
                        // 用户取消分享后执行的回调函数
                        alert('已取消');
                    }
                }
                wx.showOptionMenu();

                //分享给朋友
                wx.onMenuShareAppMessage(shareData);
                //分享到朋友圈
                wx.onMenuShareTimeline(shareData);
            })
        }
    }else{
        wx.ready(function () {

            wx.hideOptionMenu();

        })
    }


}


function shareinfo(id){
    $.ajax({
        type: "POST",
        dataType: 'json',
        data: {goods_id: id},
        url: "/index.php?m=Mobile&c=Goods&a=share_goods",
        success: function (data) {
            alert(data.msg)
        }
    });
}