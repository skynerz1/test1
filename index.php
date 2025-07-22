<?php
session_start();


include 'includes/header.php';
function saveToSearchResults($newItems) {
    $filename = 'search_results.json';

    // قراءة الملف الحالي إن وُجد
    $existing = [];
    if (file_exists($filename)) {
        $json = file_get_contents($filename);
        $data = json_decode($json, true);
        if (isset($data['posters']) && is_array($data['posters'])) {
            $existing = $data['posters'];
        }
    }

    // دمج العناصر الجديدة بدون تكرار (حسب ID)
    $ids = array_column($existing, 'id');
    foreach ($newItems as $item) {
        if (!in_array($item['id'], $ids)) {
            $existing[] = $item;
            $ids[] = $item['id'];
        }
    }

    // حفظ الملف بشكل دائم (لا يُعاد تعيينه)
    file_put_contents($filename, json_encode(['posters' => $existing], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}




function fetchSeries($type, $page = 1) {
    $baseUrl = "https://app.arabypros.com/api/serie/by/filtres/0/{$type}/{$page}/4F5A9C3D9A86FA54EACEDDD635185/d506abfd-9fe2-4b71-b979-feff21bcad13/";
    
    $headers = [
        'User-Agent: okhttp/4.8.0',
        'Accept-Encoding: gzip'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip'
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("cURL Error ($type, page $page): " . $err);
        return ['error' => 'Connection error: ' . $err];
    }

    if (empty($response)) {
        error_log("Empty response from API ($type, page $page)");
        return ['error' => 'Empty response from server'];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error ($type, page $page): " . json_last_error_msg());
        return ['error' => 'Invalid response format: ' . json_last_error_msg()];
    }

    return $data;
}

function searchMovies($query) {
    $encodedQuery = rawurlencode($query);
    $url = "https://app.arabypros.com/api/search/{$encodedQuery}/0/4F5A9C3D9A86FA54EACEDDD635185/d506abfd-9fe2-4b71-b979-feff21bcad13/";
    
    $headers = [
        'User-Agent: okhttp/4.8.0',
        'Accept-Encoding: gzip'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip'
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('Curl error: ' . $err);
        return ['error' => 'Connection error: ' . $err];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        return ['error' => 'Invalid response format: ' . json_last_error_msg()];
    }

    return $data;
}

function saveSearchResults($results) {
    $filename = 'search_results.json';
    $permanentFile = 'search_results_permanent.json';

    // تأكد أن النتائج داخل 'posters'
    $formattedResults = ['posters' => is_array($results) && isset($results['posters']) ? $results['posters'] : $results];

    // 1. حفظ الملف العادي (يُكتب من جديد كل مرة)
    file_put_contents($filename, json_encode($formattedResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 2. حفظ في الملف الدائم (لا يحذف، فقط يضيف الجديد)
    $newItems = $formattedResults['posters'];
    $existing = [];

    if (file_exists($permanentFile)) {
        $json = file_get_contents($permanentFile);
        $data = json_decode($json, true);
        if (isset($data['posters']) && is_array($data['posters'])) {
            $existing = $data['posters'];
        }
    }

    // تجنب التكرار حسب id
    $existingIds = array_column($existing, 'id');
    foreach ($newItems as $item) {
        if (!in_array($item['id'], $existingIds)) {
            $existing[] = $item;
            $existingIds[] = $item['id'];
        }
    }

    // حفظ الملف الدائم
    file_put_contents($permanentFile, json_encode(['posters' => $existing], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}


function getNewReleases() {
    $page = rand(1,10);
    $data = fetchSeries('created', $page);
    if (!isset($data['error'])) {
        saveSearchResults($data);
    }
    return $data;
}
function filterGulfSeries($seriesArray) {
    return array_filter($seriesArray, function($series) {
        if (!isset($series['genres']) || !is_array($series['genres'])) {
            return true;
        }
        foreach ($series['genres'] as $genre) {
            if (
                $genre['title'] === "مسلسلات آسيوية" ||
                $genre['title'] === "مسلسلات أجنبية" ||
                $genre['title'] === "مسلسلات تركية" 
                
            ) {
                return false;
            }
        }
        return true;
    });
}



function filterAsianSeries($seriesArray) {
    return array_filter($seriesArray, function($series) {
        if (!isset($series['genres']) || !is_array($series['genres'])) {
            return true;
        }
        foreach ($series['genres'] as $genre) {
            if (
                $genre['title'] === "مسلسلات آسيوية" ||
                $genre['title'] === "مسلسلات "
            ) {
                return false;
            }
        }
        return true;
    });
}


$seriesData = getNewReleases();
$error = '';

if (isset($_GET['search'])) {
    $searchQuery = $_GET['search'];
    $seriesData = searchMovies($searchQuery);
    if (isset($seriesData['error'])) {
        $error = $seriesData['error'];
    } else {
        saveSearchResults($seriesData);
    }
} elseif (isset($_GET['action'])) {
    if ($_GET['action'] === 'new-releases') {
        $seriesData = getNewReleases();
    } elseif ($_GET['action'] === 'popular') {
        $seriesData = fetchSeries('rating', 1);
        if (!isset($seriesData['error'])) {
            saveSearchResults($seriesData);
        }
    }
    if (isset($seriesData['error'])) {
        $error = $seriesData['error'];
    }
}

    function filterTurkishSeries($seriesArray) {
    return array_filter($seriesArray, function($series) {
        if (!isset($series['genres']) || !is_array($series['genres'])) {
            return false;
        }
        foreach ($series['genres'] as $genre) {
            if (isset($genre['title']) && $genre['title'] === "مسلسلات تركية") {
                return true;
            }
        }
        return false;
    });
}


function hasRamadan2025Genre($series) {
    if (!isset($series['genres']) || !is_array($series['genres'])) return false;

    foreach ($series['genres'] as $genre) {
        if (
            isset($genre['title']) &&
            mb_strtolower(trim($genre['title'])) === 'مسلسلات رمضان 2025'
        ) {
            return true;
        }
    }
    return false;
}

function filterRamadanKhaleeji($seriesArray) {
    $khaleejiCountries = ['السعودية', 'الامارات', 'الكويت'];

    return array_filter($seriesArray, function($series) use ($khaleejiCountries) {
        if (
            !isset($series['classification']) ||
            trim($series['classification']) === '' ||
            !hasRamadan2025Genre($series)
        ) return false;

        $classification = mb_strtolower(trim($series['classification']));

        foreach ($khaleejiCountries as $country) {
            if (mb_strpos($classification, mb_strtolower($country)) !== false) {
                return true;
            }
        }
        return false;
    });
}

function filterRamadanAraby($seriesArray) {
    $arabCountries = ['مصر', 'تونس', 'سوريا', 'العراق'];

    return array_filter($seriesArray, function($series) use ($arabCountries) {
        if (
            !isset($series['classification']) ||
            trim($series['classification']) === '' ||
            !hasRamadan2025Genre($series)
        ) return false;

        $classification = mb_strtolower(trim($series['classification']));

        foreach ($arabCountries as $country) {
            if (mb_strpos($classification, mb_strtolower($country)) !== false) {
                return true;
            }
        }

        return false;
    });
}







?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch Series</title>
    <link rel="icon" type="image/png" href="a.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
              background-color: #1c2229;
            color: #fff;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }
     
        .hero {
            background-image: url('https://assets.nflxext.com/ffe/siteui/vlv3/f841d4c7-10e1-40af-bcae-07a3f8dc141a/f6d7434e-d6de-4185-a6d4-c77a2d08737b/US-en-20220502-popsignuptwoweeks-perspective_alpha_website_medium.jpg');
            background-size: cover;
            background-position: center;
            height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hero .search-form {
            width: 100%;
            max-width: 600px;
            display: flex;
        }
        .hero .search-input {
            width: 70%;
            padding: 15px;
            font-size: 1.1rem;
            border: none;
            border-radius: 30px 0 0 30px;
            outline: none;
        }
    
        .movie-section {
            padding: 40px 0;
        }
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .movie-card {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        .movie-card:hover {
            transform: scale(1.05);
        }
        .movie-poster {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        .movie-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            padding: 10px;
            transition: transform 0.3s ease;
        }
        .movie-card:hover .movie-info {
            transform: translateY(100%);
        }
        /* .movie-details {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
        } */
        .movie-card:hover .movie-details {
            opacity: 1;
        }
        /* .btn-watch {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #008000;;
            color: #fff;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .movie-card:hover .btn-watch {
            opacity: 1;
        } */
/* .content-type {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #008000;;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
} */



        .error-message, .no-results {
            text-align: center;
            font-size: 1.2rem;
            margin: 20px 0;
        }
        .footer {
            background-color: rgba(20, 20, 20, 0.8);
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }
        .footer p {
            margin: 5px 0;
        }
        .slider-container {
    display: flex;
    overflow-x: auto;
    gap: 16px;
    scroll-behavior: smooth;
    padding-bottom: 20px;
}
.slider-container::-webkit-scrollbar {
    height: 8px;
}
.slider-container::-webkit-scrollbar-thumb {
    background: #444;
    border-radius: 4px;
}
.movie-card {
    flex: 0 0 auto;
    width: 200px;
    background: #111;
    border-radius: 12px;
    overflow: hidden;
    color: #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.5);
    transition: transform 0.3s ease;
}
.movie-card:hover {
    transform: scale(1.05);
}

    .navbar {
    background-color: rgba(20, 20, 20, 0.95);
    padding: 15px 20px;
    position: sticky;
    top: 0;
    z-index: 1000;
}
.navbar .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}
.logo {
    font-size: 1.8rem;
    font-weight: bold;
    color: #fff;
    text-decoration: none;
}
nav {
    display: flex;
    align-items: center;
}
.nav-toggle {
    display: none;
    font-size: 2rem;
    color: #fff;
    cursor: pointer;
}
.nav-links {
    list-style: none;
    display: flex;
    gap: 20px;
    margin: 0;
    padding: 0;
}
.nav-links li a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    padding: 8px 12px;
    display: inline-block;
    transition: all 0.3s ease;
}
.nav-links li:hover a {
    color: #e50914;
    transform: scale(1.05);
}

/* responsive styles */
/* @media (max-width: 768px) {
    .nav-toggle {
        display: block;
    }
    nav {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
    }
    .nav-links {
        flex-direction: column;
        width: 100%;
        display: none;
        margin-top: 10px;
    }
    .nav-links.active {
        display: flex;
    }
    .nav-links li {
        width: 100%;
    }
    .nav-links li a {
        width: 100%;
        padding: 10px 0;
    }
} */


.section-title {
  font-family: 'Cairo', sans-serif;
  font-size: 20px;
  font-weight: bold;
  color: white;
  display: flex;
  align-items: center;
}


        .category-container {
            display: flex;
            overflow-x: auto;
            gap: 16px;
            scroll-behavior: smooth;
            padding-bottom: 20px;
            padding-inline: 10px;
        }

        /* شكل السكرول */
        .category-container::-webkit-scrollbar {
            height: 8px;
        }
        .category-container::-webkit-scrollbar-thumb {
            background: #e50914;
            border-radius: 10px;
        }
        .category-container::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        /* الكروت */
        .category-card {
            flex: 0 0 auto;
            width: 200px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            text-decoration: none;
            color: white;
            box-shadow: var(--text-color);
            transition: transform 0.3s ease;
            position: relative;
        }

        /* تأثير التحويم */
        .category-card:hover,
        .category-card.active {
            transform: scale(1.05);
            box-shadow: 0 0 20px var(--2-color);
        }

        /* تحسين العرض للجوال */
        @media (max-width: 600px) {
            .category-card {
                width: 150px;
                height: 100px;
            }
        }

        /* تصميم الزر "مشاهدة الكل" */
        .view-all-button {
          display: flex;
          align-items: center;
          gap: 6px;
          color: #fff;
          font-size: 14px;
          font-weight: 500;
          text-decoration: none;
          transition: color 0.3s ease;
          cursor: pointer;
        }

        .view-all-button svg {
          fill: currentColor;
          transition: fill 0.3s ease;
        }



        .view-all-button:hover svg {
          fill: currentColor;
        }

        /* مثال لتنسيق العنوان مع زر في جهة اليمين */
        .section-title {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
          font-size: 20px;
          font-weight: bold;
          color: #fff;
        }


        .section-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
        }

        .section-title {
          font-size: 20px;
          font-weight: bold;
          color: #fff;
          margin: 0;
        }

        .view-all-button {
          display: flex;
          align-items: center;
          gap: 6px;
          color: #fff;
          font-size: 14px;
          font-weight: 500;
          text-decoration: none;
          transition: color 0.3s ease;
          cursor: pointer;
        }

        .view-all-button svg {
          fill: currentColor;
          transition: fill 0.3s ease;
        }



        .view-all-button:hover svg {
          fill: currentColor;
        }


    </style>
</head>
<body>



    <main class="main-content">
        <section class="hero">
          <div class="container">
            <form class="search-form" onsubmit="return false;">
                <input type="text" class="search-input" placeholder="شي يستاهل الانتظار" disabled style="background-color: white; color: black;" />

              <button type="button" class="search-button">قريباً</button>
            </form>
          </div>
        </section>



        
  <section id="movie-section" class="movie-section">
    <div class="container">
        <?php if (!empty($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>

        <?php elseif (isset($_GET['search']) && !empty($seriesData)): ?>
            <h2 class="section-title">نتائج البحث</h2>
            <?php 
                // لا نفلتر عند البحث
                $seriesArray = isset($seriesData['posters']) ? $seriesData['posters'] : $seriesData;
                $filteredSeries = $seriesArray;

                if (empty($filteredSeries)): ?>
                    <p class="no-results">لم يتم العثور على نتائج.</p>
                <?php else: ?>
                    <div class="movie-grid">
                        <?php foreach ($filteredSeries as $series): ?>
                            <div class="movie-card">
                                <?php if (!empty($series['sublabel'])): ?>
                                    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
                                <?php endif; ?>

                                <div class="content-type">Series</div>
                                <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                                <div class="movie-info">
                                    <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                                    <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                                </div>
                                <div class="movie-details">
                                    <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                                    <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                                    <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                                </div>
                                <?php
                                    $isMovie = isset($series['type']) && $series['type'] === 'movie';
                                    $link = $isMovie
                                        ? 'movie/links.php?id=' . urlencode($series['id'])
                                        : 'series.php?id=' . urlencode($series['id']);
                                    $label = $isMovie ? 'View Movie' : 'View Series';
                                ?>
                                <a href="<?php echo $link; ?>" class="btn-watch"><?php echo $label; ?></a>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

        <?php else: ?>


    <h1>📺 اختر المنصة</h1>

    <div class="category-container">

        <a href="browser.php?platform=shahid&page=1" class="category-card <?= $platform == 'shahid' ? 'active' : '' ?>" style="background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRlwV1US7Ou5Sa4bd8ALXdp1QVcpQV9rPRr_A&s');">

        </a>
        
        <a href="browser.php?platform=netflix&page=1" class="category-card <?= $platform == 'netflix' ? 'active' : '' ?>" style="background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQGhnm_NIUms1oIl6QLrxjZzws8wLW_MVPOyw&s');">

        </a>
        <a href="browser.php?platform=osn&page=1" class="category-card <?= $platform == 'osn' ? 'active' : '' ?>" style="background-image: url('https://play-lh.googleusercontent.com/1O4pKO7UZtF4lL61zgTeA9aoao3TRCZMgerHrvI-k0DNMvnL2-QQX63l_h2E_ayHvtU');">

        </a>
        <a href="browser.php?platform=kids&page=1" class="category-card <?= $platform == 'kids' ? 'active' : '' ?>" style="background-image: url('https://i.pinimg.com/736x/e6/84/49/e68449b851a8ffb8256a71daab209775.jpg');">

        </a>


        
      
        



        
    </div>
    
    <!-- سلايدر جديد -->
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
      <div style="font-size: 20px; font-weight: bold; color: #fff;">
        <span class="new-badge">حصري على DFkz</span>
        مسلسلات جديدة
      </div>

        <a href="cat.php?category=series&type=created" class="view-all-button">
          <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 0 24 24" width="18" fill="currentColor">
            <path d="M0 0h24v24H0V0z" fill="none"/>
            <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
          </svg>
          مشاهدة الكل
        </a>

    </div>


            <?php
                $newReleases = getNewReleases();
                $seriesArray = isset($newReleases['posters']) ? $newReleases['posters'] : $newReleases;
                $filteredSeries = filterAsianSeries($seriesArray);
                if (!empty($filteredSeries)):
            ?>
                <div class="slider-container">
                    <?php foreach ($filteredSeries as $series): ?>
                        <div class="movie-card">
                            <?php if (!empty($series['sublabel'])): ?>
    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
<?php endif; ?>

                            <div class="content-type">Series</div>
                            <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                            <div class="movie-info">
                                <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                                <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                            </div>
                            <div class="movie-details">
                                <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                                <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                                <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                            </div>
                            <a href="series.php?id=<?php echo htmlspecialchars($series['id']); ?>" class="btn-watch">View Series</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

   

    
    <!-- ✅ سلايدر جديد لمسلسلات "خليجية" بناءً على نفس الفلترة -->
    <div class="section-header">
      <h2 class="section-title">مسلسلات عربيه</h2>
      <a href="cat.php?category=series&type=created&classification=all&genre=مسلسلات%20عربية" class="view-all-button">
        <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 0 24 24" width="18" fill="currentColor">
          <path d="M0 0h24v24H0V0z" fill="none"/>
          <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
        </svg>
        مشاهدة الكل
      </a>
    </div>

    
<?php
$limit = 15; // كم مسلسل تبي تعرض
$collected = [];

for ($i = 0; $i < 3; $i++) { // نحاول 3 صفحات فقط كحد أقصى
    $page = rand(1, 20);
    $gulfData = fetchSeries('created', $page);
    $gulfArray = isset($gulfData['posters']) ? $gulfData['posters'] : $gulfData;
    $filteredGulf = filterGulfSeries($gulfArray);

    // ندمج اللي لقيناه مع الموجود
    $collected = array_merge($collected, $filteredGulf);

    // إذا وصلنا للعدد المطلوب نوقف
    if (count($collected) >= $limit) {
        break;
    }
}

// نأخذ العدد المحدد فقط
$limitedGulf = array_slice($collected, 0, $limit);

// 🟢 نحفظهم في ملف
file_put_contents('search_arab.json', json_encode(['posters' => $limitedGulf], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// الملف الدائم
$permanentFile = 'search_arab_permanent.json';

// قراءة المحتوى الحالي من الملف الدائم
$existing = [];
if (file_exists($permanentFile)) {
    $json = file_get_contents($permanentFile);
    $data = json_decode($json, true);
    if (isset($data['posters']) && is_array($data['posters'])) {
        $existing = $data['posters'];
    }
}

// دمج العناصر الجديدة بدون تكرار (حسب id مثلاً)
foreach ($limitedGulf as $newItem) {
    $found = false;
    foreach ($existing as $item) {
        if (isset($item['id']) && $item['id'] == $newItem['id']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $existing[] = $newItem;
    }
}

// حفظ في الملف الدائم بعد الدمج
file_put_contents($permanentFile, json_encode(['posters' => $existing], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));


if (!empty($limitedGulf)):
?>
<div class="slider-container">
    <?php foreach ($limitedGulf as $series): ?>
        <div class="movie-card">
            <?php if (!empty($series['sublabel'])): ?>
    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
<?php endif; ?>

            <div class="content-type">Series</div>
            <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
            <div class="movie-info">
                <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
            </div>
            <div class="movie-details">
                <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
            </div>
            <a href="series.php?id=<?php echo htmlspecialchars($series['id']); ?>" class="btn-watch">View Series</a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

    <h1>📺 اختر القناه</h1>

    <div class="category-container">

      <a href="server-live.php?id=1" class="category-card <?= $platform == 'mbc' ? 'active' : '' ?>" style="background-image: url('https://shahid.mbc.net/mediaObject/a7dcf0c9-1178-4cb9-a490-a8313975e37c?height=129&width=230&croppingPoint=&version=1&type=avif');">
      </a>

          <a href="server-live.php?id=18" class="category-card <?= $platform == 'ssc' ? 'active' : '' ?>" style="background-image: url('https://shahid.mbc.net/mediaObject/8abc6233-1ef2-443b-8de6-d401a60aa025?height=129&width=230&croppingPoint=&version=1&type=avif');">
          </a>

          <a href="server-live.php?id=19" class="category-card <?= $platform == 'bein' ? 'active' : '' ?>" style="background-image: url('https://play-lh.googleusercontent.com/BDUySDHFzY4JcRzQpLsIHiZKLvIEmVL5N30qc-DWwVhwN3dJqV0J4BKE6XH9EOw_ygQ');">
          </a>

          <a href="server-live.php?id=17" class="category-card <?= $platform == 'rot' ? 'active' : '' ?>" style="background-image: url('https://www.wesal.com.sa/public/storage/uploaded/projects/project_bwCK2gz1XfMp_2022-08-24.jpeg');">
          </a>

          <a href="server-live.php?id=16" class="category-card <?= $platform == 'wansah' ? 'active' : '' ?>" style="background-image: url('https://shahid.mbc.net/mediaObject/97613919-40eb-4032-9dcb-e940e08ae761?height=129&width=230&croppingPoint=&version=1&type=avif');">
          </a>

          <a href="server-live.php?id=13" class="category-card <?= $platform == 'ror' ? 'active' : '' ?>" style="background-image: url('https://jordandir.com/images/screenshots/1711030162.webp');">
          </a>

          <a href="server-live.php?id=8" class="category-card <?= $platform == 'qa6' ? 'active' : '' ?>" style="background-image: url('https://yt3.googleusercontent.com/pcLGQIWlrO000zyC8SEZzOmm3iZmDAmMQSNRTG28toSt9p-QX88NuiEc4GCmfXk8EwH3twcb=s900-c-k-c0x00ffffff-no-rj');">
          </a>

          <a href="server-live.php?id=14" class="category-card <?= $platform == 'dbay' ? 'active' : '' ?>" style="background-image: url('https://admango.cdn.mangomolo.com/analytics/uploads/71/5fb0fc1d19.png');">
          </a>

          <a href="server-live.php?id=15" class="category-card <?= $platform == 'dbay2' ? 'active' : '' ?>" style="background-image: url('https://admango.cdn.mangomolo.com/analytics/uploads/71/659cd942e4.png');">
          </a>

          <a href="server-live.php?id=12" class="category-card <?= $platform == '7dth' ? 'active' : '' ?>" style="background-image: url('https://yt3.googleusercontent.com/ehhpuQeVHO0g3kIPkmwrw1x0fLqDk7RyWH733oe4wcKb_1jBEMvGt4WVlQEEzcTCL6zq01K5HQ=s900-c-k-c0x00ffffff-no-rj');">
          </a>

    </div>

<!-- 🔵 سلايدر مسلسلات تركية -->
    <div class="section-header">
      <h2 class="section-title">مسلسلات تركيه</h2>
      <a href="cat.php?category=series&type=year&classification=all&genre=مسلسلات%20تركية" class="view-all-button">
        <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 0 24 24" width="18" fill="currentColor">
          <path d="M0 0h24v24H0V0z" fill="none"/>
          <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
        </svg>
        مشاهدة الكل
      </a>
    </div>
<?php
$limitTurkish = 15;
$turkishCollected = [];
$attempts = 0;

while (count($turkishCollected) < $limitTurkish && $attempts < 10) {
    $page = rand(1, 20);
    $turkishData = fetchSeries('created', $page);
    $turkishArray = isset($turkishData['posters']) ? $turkishData['posters'] : $turkishData;
    $filteredTurkish = filterTurkishSeries($turkishArray);

    foreach ($filteredTurkish as $series) {
        if (count($turkishCollected) >= $limitTurkish) break;
        if (!in_array($series['id'], array_column($turkishCollected, 'id'))) {
            $turkishCollected[] = $series;
        }
    }

    $attempts++;
}

if (!empty($turkishCollected)):

    // 🔹 تخزين مؤقت
    file_put_contents('search_results.json', json_encode(['posters' => $turkishCollected], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 🔸 تخزين دائم
    $permanentFile = 'search_results_permanent.json';
    $existing = [];

    if (file_exists($permanentFile)) {
        $json = file_get_contents($permanentFile);
        $data = json_decode($json, true);
        if (isset($data['posters']) && is_array($data['posters'])) {
            $existing = $data['posters'];
        }
    }

    foreach ($turkishCollected as $newItem) {
        $found = false;
        foreach ($existing as $item) {
            if (isset($item['id']) && $item['id'] == $newItem['id']) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $existing[] = $newItem;
        }
    }

    file_put_contents($permanentFile, json_encode(['posters' => $existing], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
?>

    <div class="slider-container">
        <?php foreach ($turkishCollected as $series): ?>
            <div class="movie-card">
                <?php if (!empty($series['sublabel'])): ?>
                    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
                <?php endif; ?>
                <div class="content-type">Series</div>
                <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                <div class="movie-info">
                    <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                    <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                </div>
                <div class="movie-details">
                    <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                    <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                    <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                </div>
                <a href="series.php?id=<?php echo htmlspecialchars($series['id']); ?>" class="btn-watch">View Series</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


    <!-- 🟢 سلايدر مسلسلات رمضان 2025 - خليجي -->
    <div class="section-header">
        <h2 class="section-title" style="display: flex; align-items: center; gap: 8px;">
          <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f31c/512.webp" alt="رمضان 2025" style="width: 32px; height: 32px;">
          مسلسلات رمضان 2025 - خليجي
        </h2>

      <a href="cat.php?category=series&type=ramadan&ramadanYear=2025&subtype=khaleeji" class="view-all-button">
        <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 0 24 24" width="18" fill="currentColor">
          <path d="M0 0h24v24H0V0z" fill="none"/>
          <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
        </svg>
        مشاهدة الكل
      </a>
    </div>

    <?php
    $limitKhaleeji = 15;
    $khaleejiCollected = [];
    $khaleejiAttempts = 0;

    while (count($khaleejiCollected) < $limitKhaleeji && $khaleejiAttempts < 10) {
        $page = rand(1, 20);
        $data = fetchSeries('created', $page);
        $array = isset($data['posters']) ? $data['posters'] : $data;
        $filtered = filterRamadanKhaleeji($array);

        foreach ($filtered as $series) {
            if (count($khaleejiCollected) >= $limitKhaleeji) break;
            if (!in_array($series['id'], array_column($khaleejiCollected, 'id'))) {
                $khaleejiCollected[] = $series;
            }
        }
        $khaleejiAttempts++;
    }

    if (!empty($khaleejiCollected)):
        file_put_contents('search_results.json', json_encode(['posters' => $khaleejiCollected], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $permanentFile = 'search_results_permanent.json';
        $existing = [];

        if (file_exists($permanentFile)) {
            $json = file_get_contents($permanentFile);
            $data = json_decode($json, true);
            if (isset($data['posters']) && is_array($data['posters'])) {
                $existing = $data['posters'];
            }
        }

        foreach ($khaleejiCollected as $newItem) {
            $found = false;
            foreach ($existing as $item) {
                if (isset($item['id']) && $item['id'] == $newItem['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $existing[] = $newItem;
            }
        }

        file_put_contents($permanentFile, json_encode(['posters' => $existing], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    ?>

    <div class="slider-container">
        <?php foreach ($khaleejiCollected as $series): ?>
            <div class="movie-card">
                <?php if (!empty($series['sublabel'])): ?>
                    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
                <?php endif; ?>
                <div class="content-type">Series</div>
                <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                <div class="movie-info">
                    <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                    <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                </div>
                <div class="movie-details">
                    <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                    <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                    <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                </div>
                <a href="series.php?id=<?php echo htmlspecialchars($series['id']); ?>" class="btn-watch">View Series</a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>


    <!-- 🟢 سلايدر مسلسلات رمضان 2025 - عربي -->
    <div class="section-header">
        <h2 class="section-title" style="display: flex; align-items: center; gap: 8px;">
          <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f31c/512.webp" alt="رمضان 2025" style="width: 32px; height: 32px;">
          مسلسلات رمضان 2025 - عربي
        </h2>

      <a href="cat.php?category=series&type=ramadan&ramadanYear=2025&subtype=araby" class="view-all-button">
        <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 0 24 24" width="18" fill="currentColor">
          <path d="M0 0h24v24H0V0z" fill="none"/>
          <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
        </svg>
        مشاهدة الكل
      </a>
    </div>

    <?php
    $limitAraby = 15;
    $arabyCollected = [];
    $arabyAttempts = 0;

    while (count($arabyCollected) < $limitAraby && $arabyAttempts < 10) {
        $page = rand(1, 20);
        $data = fetchSeries('created', $page);
        $array = isset($data['posters']) ? $data['posters'] : $data;
        $filtered = filterRamadanAraby($array);

        foreach ($filtered as $series) {
            if (count($arabyCollected) >= $limitAraby) break;
            if (!in_array($series['id'], array_column($arabyCollected, 'id'))) {
                $arabyCollected[] = $series;
            }
        }
        $arabyAttempts++;
    }

    if (!empty($arabyCollected)):
        file_put_contents('search_results.json', json_encode(['posters' => $arabyCollected], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $permanentFile = 'search_results_permanent.json';
        $existing = [];

        if (file_exists($permanentFile)) {
            $json = file_get_contents($permanentFile);
            $data = json_decode($json, true);
            if (isset($data['posters']) && is_array($data['posters'])) {
                $existing = $data['posters'];
            }
        }

        foreach ($arabyCollected as $newItem) {
            $found = false;
            foreach ($existing as $item) {
                if (isset($item['id']) && $item['id'] == $newItem['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $existing[] = $newItem;
            }
        }

        file_put_contents($permanentFile, json_encode(['posters' => $existing], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    ?>

    <div class="slider-container">
        <?php foreach ($arabyCollected as $series): ?>
            <div class="movie-card">
                <?php if (!empty($series['sublabel'])): ?>
                    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
                <?php endif; ?>
                <div class="content-type">Series</div>
                <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                <div class="movie-info">
                    <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                    <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                </div>
                <div class="movie-details">
                    <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                    <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                    <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                </div>
                <a href="series.php?id=<?php echo htmlspecialchars($series['id']); ?>" class="btn-watch">View Series</a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>


<!-- =========================== -->

<!-- كود قائمتي -->

<?php

require_once 'functions.php';

$favorites = [];
if (isset($_SESSION['favorites']) && is_array($_SESSION['favorites'])) {
    foreach ($_SESSION['favorites'] as $favId) {
        $details = getSeriesDetails($favId);
        if ($details) {
            $favorites[] = $details;
        }
    }
}
?>

<?php if (!empty($favorites)): ?>
    <h2 class="section-title">
      <img src="/512.webp" alt="قرد" style="width:1em; height:1em; vertical-align:middle;">
      قائمتي
    </h2>

    <div class="slider-container">
        <?php foreach ($favorites as $series): ?>
            <div class="movie-card">
                <?php if (!empty($series['sublabel'])): ?>
                    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
                <?php endif; ?>

                <div class="content-type">Series</div>
                <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                <div class="movie-info">
                    <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                    <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                </div>
                <div class="movie-details">
                    <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                    <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                    <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                </div>
                <a href="series.php?id=<?php echo htmlspecialchars($series['id']); ?>" class="btn-watch">View Series</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>



            <!-- سلايدر الأكثر شهرة -->
    <div class="section-header">
        <h2 class="section-title">
          الاكثر شهره
          <img src="https://em-content.zobj.net/source/animated-noto-color-emoji/356/fire_1f525.gif" alt="🔥" style="width:24px; height:24px; vertical-align:middle; margin-left:8px;">
        </h2>

      <a href="cat.php?category=series&type=rating" class="view-all-button">
        <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 0 24 24" width="18" fill="currentColor">
          <path d="M0 0h24v24H0V0z" fill="none"/>
          <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
        </svg>
        مشاهدة الكل
      </a>
    </div>
            <?php
                $popularData = fetchSeries('rating', 1);
                $popularArray = isset($popularData['posters']) ? $popularData['posters'] : $popularData;
                $filteredPopular = filterAsianSeries($popularArray);
                if (!empty($filteredPopular)):
            ?>
                    <div class="slider-container">
                        <?php foreach ($filteredPopular as $series): ?>
                            <div class="movie-card">
                                <?php if (!empty($series['sublabel'])): ?>
    <div class="movie-sublabel"><?php echo htmlspecialchars($series['sublabel']); ?></div>
<?php endif; ?>

                                <div class="content-type">Series</div>
                                <div class="top-ser">Top Series</div>

                                <img src="<?php echo htmlspecialchars($series['image']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster" loading="lazy">
                                <div class="movie-info">
                                    <h3 class="movie-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                                    <p class="movie-year"><?php echo htmlspecialchars($series['year']); ?></p>
                                </div>
                                <div class="movie-details">
                                    <p>Year: <?php echo htmlspecialchars($series['year']); ?></p>
                                    <p>IMDB: <?php echo htmlspecialchars($series['imdb']); ?></p>
                                    <p>Classification: <?php echo htmlspecialchars($series['classification']); ?></p>
                                </div>
                                <?php
                                    $isMovie = isset($series['type']) && $series['type'] === 'movie';
                                    $link = $isMovie
                                        ? 'movie/links.php?id=' . urlencode($series['id'])
                                        : 'series.php?id=' . urlencode($series['id']);
                                    $label = $isMovie ? 'View Movie' : 'View Series';
                                ?>
                                <a href="<?php echo $link; ?>" class="btn-watch"><?php echo $label; ?></a>

                            </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>


    </main>



    <script>
          function toggleNav() {
            const nav = document.getElementById("navLinks");
            nav.classList.toggle("active");
          }
        
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-links a:not(.external-link)');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newMovieSection = doc.getElementById('movie-section');
                            if (newMovieSection) {
                                document.getElementById('movie-section').innerHTML = newMovieSection.innerHTML;
                                document.getElementById('movie-section').scrollIntoView({ behavior: 'smooth' });
                            }
                        });
                });
            });

            // Handle long press for Movies link
            const movieLink = document.querySelector('.nav-links a.external-link');
            if (movieLink) {
                let pressTimer;
                const longPressDuration = 50; // 0.5 seconds

                // Mouse events
                movieLink.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    pressTimer = setTimeout(() => {
                        window.location.href = movieLink.getAttribute('href');
                    }, longPressDuration);
                });

                movieLink.addEventListener('mouseup', function() {
                    clearTimeout(pressTimer);
                });

                movieLink.addEventListener('mouseleave', function() {
                    clearTimeout(pressTimer);
                });

                // Touch events for mobile
                movieLink.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    pressTimer = setTimeout(() => {
                        window.location.href = movieLink.getAttribute('href');
                    }, longPressDuration);
                });

                movieLink.addEventListener('touchend', function() {
                    clearTimeout(pressTimer);
                });

                movieLink.addEventListener('touchcancel', function() {
                    clearTimeout(pressTimer);
                });

                // Prevent regular click
                movieLink.addEventListener('click', function(e) {
                    e.preventDefault();
                });
            }

            const form = document.querySelector('.search-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                fetch('?' + new URLSearchParams(formData).toString())
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newMovieSection = doc.getElementById('movie-section');
                        if (newMovieSection) {
                            document.getElementById('movie-section').innerHTML = newMovieSection.innerHTML;
                            document.getElementById('movie-section').scrollIntoView({ behavior: 'smooth' });
                        }
                    });
            });
        });
          document.querySelectorAll('a').forEach(link => {
            let timer;

            link.addEventListener('touchstart', e => {
              // شغّل مؤقت الضغط المطوّل
              timer = setTimeout(() => {
                window.location.href = link.href; // يعتبرها نقرة
              }, 300); // بعد 300 مللي ثانية يدخل
            });

            link.addEventListener('touchend', e => {
              clearTimeout(timer); // إذا ترك قبل 300ms، ما يسوي شي
            });

            link.addEventListener('contextmenu', e => {
              e.preventDefault(); // يمنع ظهور قائمة "فتح في متصفح"
            });
          });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
