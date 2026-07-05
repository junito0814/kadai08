<?php
//エラー表示
ini_set("display_errors",1);

function h ($str){
    //htmlspecilchars安全な文字に変換、ENT_QUOTESで"",''を無害化
    return htmlspecialchars($str,ENT_QUOTES); 
}

//db接続
// localhost用
// include("funcs.php");
// $pdo = db_conn();

// さくら用
include("funcs.php");
$pdo = db_conn_sakura();


//sql作成
$stmt = $pdo->prepare("SELECT * FROM trip ORDER BY indate DESC");
$status = $stmt->execute();

//データ表示
$view = "";
if($status == false){
    $error = $stmt->errorInfo();
    exit("SQL Error:".$error[2]);
}

//全データ取得
$values = $stmt->fetchAll(PDO::FETCH_ASSOC);

//写真データをまとめて取得し、trip_idごとに振り分ける
if (count($values) > 0) {

    //取得した投稿のidを配列にする（例：[1, 2, 3]）
    $tripIds = array_column($values, 'id');

    //IN句用に「?, ?, ?」のようなプレースホルダーを人数分作る
    $placeholders = implode(',', array_fill(0, count($tripIds), '?'));

    //該当する投稿すべての写真を1回でまとめて取得
    $photoStmt = $pdo->prepare("SELECT trip_id, path FROM photos WHERE trip_id IN ($placeholders)");
    $photoStmt->execute($tripIds);
    $allPhotos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

    //trip_idごとに写真パスをグループ分けする
    $photosByTrip = [];
    foreach ($allPhotos as $photo) {
        $photosByTrip[$photo['trip_id']][] = $photo['path'];
    }

    //各投稿データに、対応する写真配列を追加する
    foreach ($values as &$info) {
        $info['photos'] = $photosByTrip[$info['id']] ?? [];
    }
    unset($info); //参照渡しのループ後はunsetしておくのが定石
}



// 直近１週間分のデータ（マップのみ）
$mapStmt = $pdo -> prepare("SELECT * FROM trip WHERE indate >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$mapStmt->execute();
$weeklyValues = $mapStmt -> fetchAll(PDO::FETCH_ASSOC);

// マップ用にlat,lng,spotNameのみ取得
$mapPoints = [];
foreach($weeklyValues as $info){
    $mapPoints[] = [
        "lat" => (float)$info["lat"],
        "lng" => (float)$info["lng"],
        "spotName" => $info["spotName"]
    ];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="style.js"></script>
    <script>
        (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})
        ({key: <?= json_encode(google_maps_api_key()) ?>, v: "weekly", libraries: "maps,marker"});
    </script>
    <title>SNS</title>
</head>
<body>
<header>
    <div class="menu">
        <h2 class="menuTitle">T R I P</h2>
        <button id="toHome">HOME</button>
        <button id="toPost">POST</button>
    </div>
</header>

    <h1 id="title">S N S</h1>

    <div id="weeklyMap"></div>

    <div class="search">
        <label for="search">
            <input type="text" id="search" placeholder="キーワード検索">
        </label>
    </div>

    <div class="cardContainer">
        <?php foreach($values as $info){ ?>

        <div class="card"  data-id = <?= $info["id"] ?>>
            <div>
                <h3><?= h($info["spotName"]) ?></h3>
            </div>

            <div>
                <p>日付：<?= h($info["tripDate"]) ?></p>         
            </div>

            <div>
                <p>滞在：<?= h($info["spendTime"]) ?> 時間</p>
            </div>

            <div>
                <p>費用：<?= h($info["cost"]) ?> 円</p>
            </div>

            <div>
                <p>評価：<?= str_repeat('⭐️',$info["score"])?></p>
            </div>

            <div>
                <p>感想：<?= h($info["comment"]) ?></p>
            </div>

            <?php if (!empty($info["photos"])): ?>
            <div class="photoScroll">
                <?php foreach ($info["photos"] as $photoPath): ?>
                    <img src="<?= h($photoPath) ?>" alt="<?= h($info["spotName"]) ?>" class="photoItem">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>  
                
            <div class="likeArea">
                <button class="likeBtn" data-id="<?= h($info["id"]) ?>">❤️</button>
                <span class="likeCount"><?= h($info["likeCount"]) ?></span>
            </div>
                
        </div>      

        <?php } ?>
    </div>
<script>
    // detail.phpへ
    $(document).on('dblclick',".card",function(){
        const id = $(this).data("id");
        window.location.href ="detail.php?id="+id;
    });

    // post.phpへ
    $("#toPost").on('click',function(){
        window.location.href="post.php";
    });

        //キーワード検索
    $("#search").on("keyup",function(){
        const keyword = $(this).val().toLowerCase();
        $(".card").each(function(){
            const text = $(this).text().toLocaleLowerCase();

            if(text.includes(keyword)){
                $(this).show();
            }else{
                $(this).hide();
            }
        })
    });


    //一週間分の緯度軽度データ操作
    // phpの配列をjson消え
    const mapPoints = <?= json_encode($mapPoints) ?>;

    let weeklyMap;

    // １週間以内に投稿されたもののマップの初期化
   async function initWeeklyMap(){

        // 地図機能、マーカー機能の読み込み
        const {Map} = await google.maps.importLibrary("maps");
        const {AdvancedMarkerElement} = await google.maps.importLibrary("marker");

        // データがなかったらここを表示（東京駅付近）
        const defaultPos = {lat: 35.681236, lng: 139.767125}; 

        weeklyMap = new google.maps.Map(document.getElementById('weeklyMap'),{
            center: defaultPos,
            zoom: 14,
            mapId: "DEMO_MAP_ID"
        });

        // データがなければここ（東京駅付近のマップ表示）で終了
        if(mapPoints.length === 0){
            return;
        }

        //boundsはマップの大枠
        const bounds = new google.maps.LatLngBounds();

        mapPoints.forEach(function(point){

            // 緯度・経度を取り出す（googlemapが読み込めるlat,lngに）
            const position = {lat: point.lat, lng: point.lng};

            // 上記で取得した緯度・経度にピンさす
            const marker = new AdvancedMarkerElement({
                position: position,
                map: weeklyMap,
                title: point.spotName
            });

              // ピンをクリックした時のポップアップ作成（infoWindowに保存）
                const infoWindow = new google.maps.InfoWindow({
                    content: `<div style="font-size: 18px;">${point.spotName}</div>`,
                    disableAutoPan: true
                });

                // マウスカーソルがピンの上に入りinfoWindowを表示
                marker.addEventListener('mouseenter', function(){
                    infoWindow.open({
                        anchor: marker,
                        map: weeklyMap
                    });
                });

                // マウスカーソルが外れたらポップアップを閉じる
                marker.addEventListener('mouseleave', function(){
                    infoWindow.close();
                });

                bounds.extend(position);
            });

        //ピンが複数ある場合は、全部が画面に収まるよう調整
        if(mapPoints.length > 1){
            weeklyMap.fitBounds(bounds);
        }else{
            // ピンが一件の場合はそこを中心にズーム
            weeklyMap.setCenter(bounds.getCenter());
            weeklyMap.setZoom(15);
        }
   }

   $(document).ready(function(){
    if(typeof google !== 'undefined' && google.maps){
        initWeeklyMap();
    }
   });

   // いいねボタン
$(document).on('click', '.likeBtn', function(e){
    e.stopPropagation(); // カードのクリック（詳細ページへの遷移）が発動しないようにする
    const $btn = $(this);
    const id = $btn.data("id");

    //like.phpにのみidを飛ばす
    $.post("like.php", { id: id }, function(response){
        // いいね数の表示を再読み込みなしで行う
        $btn.siblings(".likeCount").text(response.likeCount);
    }, "json");
});


</script>
</body>
</html>