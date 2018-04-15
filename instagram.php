<?php

header('Content-Type: application/json');

$id = "";
$doc = false;
$error = false;
$data = false;
$result = array();

// deal with input
if (isset($_REQUEST['url']) && $_REQUEST['url'] != '')
{
  $url = $_REQUEST['url'];
  if (preg_match("/^(https?\:\/\/)?www\.instagram\.com\/p\/([A-z0-9_-]+)\/?.*$/", $url, $matches))
  {
    $id = $matches[2];
  }
  else
  {
    $error = "Invalid URL";
  }
}
else
{
  $error = "Empty parameter 'url' or 'p'";
}

// fetch info
if (!$error && $id != '')
{
  // the author info
  for ($i = 0; $i < 2; ++$i)
  {
    if ($i !== 0) sleep(1);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.instagram.com/oembed/?url=https://www.instagram.com/p/$id/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $json = curl_exec($ch);
    // $json = file_get_contents("https://api.instagram.com/oembed?url=http://www.instagram.com/p/a$id/");
    if ($json)
    {
      if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200)
      {
        $error = false;
        $data = json_decode($json, true);
      }
      else
      {
        $error = $json;
      }
      break;
    }
    else
    {
      $error = 'Server fault';
    }
    curl_close($ch);
  }

  // get image or video link from the post page
  if (!$error)
  {
    $page = false;

    for ($i = 0; $i < 2; ++$i)
    {
      if ($i !== 0) sleep(1);
      $page = file_get_contents("https://www.instagram.com/p/$id/");
      if ($page !== false) break;
    }

    if ($page)
    {
      $doc = new DOMDocument();
      $doc->loadHTML($page);
    }
    else
    {
      $error = 'Server fault';
    }
  }
}

//
if (!$error && $doc)
{
  $meta = array();
  $meta_DOM = $doc->getElementsByTagName('meta');

  // normallize data
  foreach ($meta_DOM as $m)
  {
    $m_key = false;
    $m_value = '';
    foreach ($m->attributes as $attr)
    {
      $key = $attr->name;
      $value = $attr->value;
      if ($key == 'name' || $key == 'property')
        $m_key = $value;
      else if ($key == 'content')
        $m_value = $value;
      $meta[$key] = $value;
    }
    if ($m_key) $meta[$m_key] = $m_value;
  }
  unset($meta['name']);
  unset($meta['property']);
  unset($meta['content']);

  if (isset($meta['og:type']))
  {
    $type = $meta['og:type'];
    if ($type == 'instapp:photo') $type = 'image';

    if (isset($meta["og:$type"]))
    {
      $url = $meta["og:$type"];
      $result['status'] = 'ok';
      if ($data !== false && isset($data['author_name']))
        $result['author_name'] = $data['author_name'];
      $result['post_url'] = "https://www.instagram.com/p/$id/";
      if ($data !== false && isset($data['thumbnail_url']))
        $result['thumbnail_url'] = $data['thumbnail_url'];
      $result['media_type'] = $type;
      $result['media_url'] = $url;
    }
    else
    {
      $error = '2';
    }
  }
  else
  {
    $error = '1';
  }
}

if ($error)
{
  $result['status'] = 'fail';
  $result['reason'] = $error;
}

// response the result as JSON
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);