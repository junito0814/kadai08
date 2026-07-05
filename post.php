<?php
require_once __DIR__ . '/funcs.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <?= google_maps_script_tag() ?>

    <title>Post</title>
</head>
<body>
    <header>
        <div class="menu">
            <h2 class="menuTitle">T R I P</h2>
            <button id="toHome">HOME</button>
            <button id="toPost">POST</button>
        </div>
    </header>

    <h1 id="title">P O S T</h1>

    <div id="postForm">
        <form method="post" action="insert.php" enctype="multipart/form-data" onkeydown="if(event.key === 'Enter'){return false;}">    
            <div id="place" class="postTitle">
                <label>行き先</label>
                <gmp-place-autocomplete id="autocomplete-container">
                    <input type="text" name="spotName" id="spotName">
                </gmp-place-autocomplete>   

                <div id="setMap"></div>
                <!-- phpでデータ渡すときに座標が必要なため、以下を用意 -->
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
            </div>

            <div class="postTitle">
                <label>日付</label>
                <div><input type="date" name="tripDate" class="formInput" id="tripDate"></div>
            </div>

            <div class="postTitle">
                <label>滞在時間</label>
                <div><input type="number" name="spendTime" class="formInput" id="spendTime"> 時間</div>
            </div>

            <div class="postTitle">
                <label>費用</label>
                <div><input type="number" name="cost" class="formInput" id="cost">　円</div>
            </div>

            <div id="star" class="postTitle">
                <label>評価</label>
                <div>
                <input type="hidden" name="score" id="scoreInput">
                <span class="star">⭐️</span>
                <span class="star">⭐️</span>
                <span class="star">⭐️</span>
                <span class="star">⭐️</span>
                <span class="star">⭐️</span>
                </div>
            </div>

            <div class="postTitle">
                <label>感想</label>
                <div><textarea name="comment" id="comment" id="comment"></textarea></div>
            </div>

            <div class="postTitle">
                <label>写真</label>
                <div><input type="file" name="photo[]" id="photo" accept="image/*" multiple></div>
            </div>

            <input type="submit" value="UPLOAD" class="submitBtn">
        </form>
    </div>

    <script>
        //マップ機能
        let map;
        let marker = null;
        let AdvancedMarkerElementClass ;

        function showMapUnavailable(message) {
            const target = document.getElementById('setMap');
            if (target) {
                target.innerHTML = '<p style="padding:12px;color:#666;">' + message + '</p>';
            }
        }

        // フォームの誤送信を防ぐ（検索窓のエンターは通す）
        $(document).on("keydown", "#postForm", function(e) {
            if ((e.which === 13 || e.key === 'Enter')) {
                // テキストエリア、またはGoogleの検索窓の中でのエンターなら送信を防止しない
                if ($(e.target).is('textarea') || $(e.target).closest('gmp-place-autocomplete').length > 0) {
                    return true; 
                }
                e.preventDefault();
                return false;
            }
        });

        // マップを画面に出す
        async function initMap(){

            //使う機能をgoogleからインポート
            // awaitを使うことで順番に確実に読み込んでから次に進む
            const {Map} = await google.maps.importLibrary("maps");
            const {AdvancedMarkerElement} = await google.maps.importLibrary("marker");
            const {PlaceAutocompleteElement} = await google.maps.importLibrary("places");

            AdvancedMarkerElementClass = AdvancedMarkerElement;

            // マップ初期値（東京駅付近）
            const defaultPos = {lat: 35.681236, lng: 139.767125};

            // 地図オブジェクトを作成し、#setMapに入れる
            map = new google.maps.Map(document.getElementById('setMap'),{
                    center: defaultPos,
                    zoom: 7,
                    mapId: "DEMO_MAP_ID"
            });

            // 行き先のとこを検索欄にする
            const autocompleteComponent = document.getElementById('autocomplete-container');

            // Googleの検索窓（Shadow DOM）の内部まで潜り込んでエンター送信を強制ブロックする
            autocompleteComponent.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation(); // 上位のフォームに「エンターが押されたよ」と伝わるのを遮断
            }
        }, true);

        // 検索結果のリストから場所を選択したものを監視
        autocompleteComponent.addEventListener('gmp-select', async function(e){
            const { placePrediction } = e;
            const place = placePrediction.toPlace();

            try {
                // 座標取得
                await place.fetchFields({fields: ['location', 'displayName']});

                // 座標情報が取得できなかった時のためのエラー防止
                if(!place.location){
                    return;
                } 

                // 取得した座標をlocationに入れる
                const location = place.location;

                // 場所の名前がわかっていれば行き先（spotName）に入れる
                if(place.displayName){
                    $("#spotName").val(place.displayName);
                }

                // 地図中心移動
                map.setCenter(location);
                map.setZoom(17);

                // 選んだ場所にピンを指す
                createOrMoveMarker(location);
                
                // エラーが起きればconsole.logにエラー表示
            }catch (error){
                console.error("データ取得失敗",error)
            }
        });    

                // 手動でピンを指す
            map.addListener('click', function(e){
                createOrMoveMarker(e.latLng);
            });
        }

            function createOrMoveMarker(latLng){
                if(marker === null){
                    // まだピンがなかったら新しく地図に置く
                    marker = new AdvancedMarkerElementClass({
                        position: latLng,
                        map: map,
                        gmpDraggable: true  //ドラッグ移動許可
                    });

                    marker.addListener('dragend', function(e){
                        const pos = marker.position;
                        $("#lat").val(typeof pos.lat ==='function' ? pos.lat() : pos.lat);
                        $("#lng").val(typeof pos.lng ==='function' ? pos.lng() : pos.lng);
                    })
                }else{
                    // すでにピンがあれば、新しくピンを刺さずにそれを移動
                    marker.position = latLng;
                }

                // 隠し入力欄のlat,lngに数値セット
                const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
                const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;
                $("#lat").val(lat);
                $("#lng").val(lng);
            }
        
            // ページ読み込みが完了すれば以下を実行
            $(document).ready(function(){
                if (window.__GOOGLE_MAPS_API_KEY_MISSING__) {
                    showMapUnavailable('Google Maps APIキーが未設定のため、地図を表示できません。');
                    return;
                }

                if(typeof google !== 'undefined' && google.maps){
                    initMap();
                } else {
                    showMapUnavailable('Google Maps の読み込みに失敗しました。APIキーまたはネットワークを確認してください。');
                }
            });

        // 星評価
        let rating =0;
        $(".star").on('click',function(){
            rating = $(".star").index(this)+1;
            $("#scoreInput").val(rating);
            updateStars();
        });

        function updateStars(){
            $(".star").each(function(index){
                if(index < rating){
                    $(this).css('opacity','1');
                }else{
                    $(this).css('opacity','0.3')
                }
            });
        }

        // メニューボタン
        $("#toHome").on('click',function(){
            window.location.href="sns.php";
        });

        $(".submitBtn").on('click',function(e){
            const inputInfo = [
                $("#spotName").val(),
                $("#lat").val(),
                $("#lng").val(),
                $("#tripDate").val(),
                $("#spendTime").val(),
                $("#cost").val(),
                $("#scoreInput").val(),
                $("#comment").val()
            ];

            const empty = inputInfo.some(function(value){
                return value === "";
            });

            if (empty){
                alert("すべて入力してください");
                e.preventDefault();
            }
        });

    </script>    
    </body>

</html>