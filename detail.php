<?php
//エラー表示
ini_set("display_errors",1);

// XSS対策用：エスケープ処理の関数
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

//db接続
// localhost用
include("funcs.php");
$pdo = db_conn();

// さくら用
// include("funcs.php");
// $pdo = db_conn_sakura();


// id受け取り
$id = $_GET["id"] ?? null;  

// idなし、数値でない場合処理ストップ
if($id === null || !is_numeric($id)){
    exit("ダメー");
}

//sql作成
$stmt = $pdo->prepare("SELECT * FROM trip WHERE id = :id");
$stmt -> bindValue(":id", $id, PDO::PARAM_INT);
$status = $stmt->execute();

//データ表示
$view = "";
if($status == false){
    $error = $stmt->errorInfo();
    exit("SQL Error:".$error[2]);
}

//1件データ取得
$values = $stmt->fetch(PDO::FETCH_ASSOC);

//この投稿に紐づく写真をすべて取得
$photoStmt = $pdo->prepare("SELECT path FROM photos WHERE trip_id = :id");
$photoStmt->bindValue(":id", $id, PDO::PARAM_INT);
$photoStmt->execute();
$photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

// マップ用にlat,lng,spotNameのみ取得
    $mapPoints[] = [
        "lat" => (float)$values["lat"],
        "lng" => (float)$values["lng"],
        "spotName" => $values["spotName"]
    ];

        // JavaScriptに安全にデータを渡すためにJSON化
    $jsonMapPoints = json_encode($mapPoints, JSON_UNESCAPED_UNICODE);
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
    <title>detail</title>
</head>
<body>
<header>
    <div class="menu">
        <h2 class="menuTitle">T R I P</h2>
        <button id="toHome">HOME</button>
        <button id="toPost">POST</button>
    </div>
</header>

<div id="detailCard">
    <div>
        <h1><?= h($values["spotName"]) ?></h1>
    </div>

    <div>
        <p class = "infoD">日付：<?= h($values["tripDate"]) ?></p>
    </div>

    <div>
        <p class = "infoD">滞在：<?= h($values["spendTime"]) ?> 時間</p>
    </div>

    <div>
        <p class = "infoD">費用：<?= h($values["cost"]) ?> 円</p>
    </div>

    <div>
        <p class = "infoD">評価：<?= str_repeat("⭐️", $values["score"]) ?></p>
    </div>

    <div>
        <p class = "infoD">感想：<?= h($values["comment"]) ?></p>
    </div>
    
    <?php if (!empty($photos)): ?>
        <div class="photoScroll">
            <?php foreach ($photos as $photoPath): ?>
                <img src="<?= h($photoPath) ?>" alt="<?= h($values["spotName"]) ?>" class="photoItem">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div id="mapD"></div>

    <div id="cardMenu">
        <div class="likeAreaD">
            <button class="likeBtnD" data-id="<?= h($values["id"]) ?>">❤️</button>
            <span class="likeCountD"><?= h($values["likeCount"]) ?></span>
        </div>

        <div id="custom">
            <a id="toUpdate" href="update.php?id=<?= h($values["id"]) ?>">更新</a>
            <a id="toDelete" href="#" class="deleteLink" data-id="<?= h($values["id"]) ?>">削除</a>
        </div>
    </div>

<script>
// マップ処理
let mapD;

// PHPから受け取る
const points = <?= $jsonMapPoints ?>;
const point = points[0];

const position = {
    lat: point.lat,
    lng: point.lng
};

async function initMap() {

    const { Map } = await google.maps.importLibrary("maps");
    const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

    const position = {
        lat: point.lat,
        lng: point.lng
    };

    mapD = new Map(document.getElementById("mapD"), {
        center: position,
        zoom: 13,
        mapId: "DEMO_MAP_ID"
    });

    new AdvancedMarkerElement({
        position: position,
        map: mapD
    });
}

initMap();

// 削除ページへ
$(".deleteLink").on('click', function(e){
    e.preventDefault();  // aタグ本来の「#へ移動」を止める

    const id = $(this).data("id");
    if (confirm("本当に削除しますか？")) {
        window.location.href = "delete.php?id=" + id;
    }
});

// いいね機能
$(".likeBtnD").on('click', function(){
    const id = $(this).data("id");

    $.post("like.php", { id: id }, function(response){
        $(".likeCountD").text(response.likeCount);
    }, "json");
});

// メニュー処理
$("#toHome").on('click',function(){
    window.location.href="sns.php";
});

$("#toPost").on('click',function(){
    window.location.href="post.php";
});

</script>
</body>
