<?php

//this file should be moved out the root directory
//$env is an instance of a Fluent object containing the $_ENV variables
$arr = [
    "db" => [
        'driver'    => 'mysql',
        "database"=>$env->get('dbname'),
        "host"=>$env->get('dbhost'),
        "port"=>$env->get('dbport', 3306),
        "username"=>$env->get('dbuser'),
        "password"=>$env->get('dbpass'),
        "cert"=>$env->get('dbcert')
    ],
    "token" => [
        "jwt_key"=>$env->get("jwtkey"),
        "expire_minutes"=>$env->get("jwtexpireminutes", 15),
        "refresh_minutes"=>$env->get("jwtrefreshminutes", 86400),
        "encoding"=>$env->get("jwtencoding", 'HS512')
    ],
    "aws" => [
        "s3bucket"=>$env->get("s3bucket"),
        "s3path"=>$env->get("s3path"),
        "sqsarn"=>$env->get("sqsarn"),
        "cfdistributionid"=>$env->get("cfdistid"),
        "settings"=> [
            "region" =>"eu-west-1",
            "version" => "latest",
            "credentials"=>[
                "user"=>$env->get("awsuser"),
                "pass"=>$env->get("pass")
            ]
        ]
    ],
    "filesystems.default" => ["driver"=>"s3"],
    "filesystems.disks.s3" => [
        'driver' => 's3',
        'key' => $env->get('AWS_ACCESS_KEY_ID'),
        'secret' => $env->get('AWS_SECRET_ACCESS_KEY'),
        'region' => $env->get('AWS_DEFAULT_REGION'),
        'bucket' => $env->get('s3bucket'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'prefix_path' => $env->get("s3path", "")
    ],
    "cors" => [
        "headers" => [
            "Content-Type",
            "X-Requested-With",
            "X-Force-Auth-Cookies",
            "Accept",
            "Origin",
            "Authorization",
            "Referer",
            "sec-ch-ua",
            "sec-ch-ua-mobile",
            "User-Agent"
        ],
        "origin" => 0
    ]
];

if (isset($_SERVER['HTTP_ORIGIN']) AND $_SERVER['HTTP_ORIGIN']) $arr["cors"]["origin"] = $_SERVER['HTTP_ORIGIN'];
else if (isset($_SERVER['HTTP_REFERER'])) $arr["cors"]["origin"] = $_SERVER['HTTP_REFERER'];

return $arr;


