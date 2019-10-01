<?php

namespace app\Controllers;
use \ArrayObject;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Cassandra\Timeuuid;
use Cassandra\Uuid;
use Cassandra\Map;
use Cassandra\ExecutionOptions;
use Cassandra;

class BaseController {

    public $INVALID_DATA_RESPONSE_CODE = 422;
    public $BASE_SITE_URL = 'https://www.cushy.com';
    
    public function testBaseContoller(ServerRequestInterface $request, ResponseInterface $response, $args) {
        return "Good Point";
    }
    
    public function calculateLimit($page,$count){
        return $page*$count;
    }
    
    public function calculateOffset($count, $limt){
        return $limt - $count;
    }

    public function getPage(ServerRequestInterface $request){
        $page = 1;
        if($request->getQueryParam('page') != null){
            $page = $request->getQueryParam('page');
        }
        return $page;
    }
    
    public function getCount(ServerRequestInterface $request, $count=20){
        if($request->getQueryParam('count') != null){
            $count = $request->getQueryParam('count');
        }
        return $count;
    }
    
    public function getLimit(ServerRequestInterface $request){
        $limit = 20;
        if($request->getQueryParam('limit') != null){
            $limit = $request->getQueryParam('limit');
        }
        return $limit;
    }
   
    public function getSelectColumns (ServerRequestInterface $request){
        $selectColumns = '*';
        if($request->getQueryParam('select') != null){
            $selectColumns = $request->getQueryParam('select');
        }
        return $selectColumns;
    }
    
    public function getMaxDistance (ServerRequestInterface $request){
        $maxDistance = null;
        if($request->getQueryParam('max_distance') != null){
            $maxDistance = $request->getQueryParam('max_distance');
        }
        return $maxDistance;
    }
    
    public function getLatitude (ServerRequestInterface $request){
        $latitude = '';
        if($request->getQueryParam('latitude') != null){
            $latitude = $request->getQueryParam('latitude');
        }
        return $latitude;
    }
    
    public function getLongitude (ServerRequestInterface $request){
        $longitude = '';
        if($request->getQueryParam('longitude') != null){
            $longitude = $request->getQueryParam('longitude');
        }
        return $longitude;
    }
    
    public function getCityLatitude (ServerRequestInterface $request){
        $latitude = '';
        if($request->getQueryParam('city_latitude') != null){
            $latitude = $request->getQueryParam('city_latitude');
        }
        return $latitude;
    }
    
    public function getCityLongitude (ServerRequestInterface $request){
        $longitude = '';
        if($request->getQueryParam('city_longitude') != null){
            $longitude = $request->getQueryParam('city_longitude');
        }
        return $longitude;
    }
    
    public function getLocationName (ServerRequestInterface $request){
        $location;
        if($request->getQueryParam('location') != null){
            $location = $request->getQueryParam('location');
            $location = strtolower($location);
            $location = str_replace("'","''",$location);
            $location = str_replace('"','',$location);
            $location = '*'.$location.'*';
        } else {
            // $location = '*';
        }
        return $location;
    }
    
    public function getTag (ServerRequestInterface $request){
        $location;
        if($request->getQueryParam('tag') != null){
            $tag = $request->getQueryParam('tag');
            $tag = strtolower($tag);
            $tag = '*'.$tag.'*';
        } else {
            // $tag = '*';
        }
        return $tag;
    }
    
    public function changeDataFormat($result){
        $rdata = array();
        foreach ($result as $row) {
            $tempArray = array();
            foreach ($row as $key => $value) {
                
                if(gettype($value) === "object") {
                    if(get_class($value) === "Cassandra\Uuid") {
                        $tempArray[$key] = (String)$value;
                    } else if(get_class($value) === "Cassandra\Timeuuid") {
                        $tempArray[$key] = (String)$value;
                    } else if(get_class($value) === "Cassandra\Float") {
                        $tempArray[$key] = (String)$value;
                    } else if(get_class($value) === "Cassandra\Bigint" OR get_class($value) === "Cassandra\Tinyint") {
                        $tempArray[$key] = (String)$value;
                    } else if(get_class($value) === "Cassandra\Timestamp") {
                        $tempArray[$key] = (String)$value;
                    } else if(get_class($value) === "Cassandra\Map") {
                        $mapData = array();
                       /*  foreach ($value as $subKey => $subVal){
                            $mapData[$subKey] = $subVal;
                        } */
                        foreach ($value as $subKey => $subVal){
                            // Check is map is nested of collection
                            if (gettype($subVal) !== "object") {
                                $mapData[$subKey] = $subVal;
                            }
                            else {
                                // If map nested collection. (ie: working_schedule)
                                $subChildData = [];
                                foreach ($subVal as $subChildKey => $subChildVal){
                                    if (!empty($subChildVal)) {
                                        foreach ($subChildVal as $lastkey => $lastVal) {
                                            if($subChildKey =='start_time' || $subChildKey == 'end_time'){
                                                $subChildData[$subChildKey] = $lastVal; #date("H:i:s", $lastVal/1000);
                                            }else{
                                                #$subChildKey = substr($subChildKey,0,15);
                                                $subChildData[(string)$subChildKey] = $lastVal;
                                            }
                                        }
                                    }
                                }
                                
                                $mapData[$subKey] = $subChildData;
                            }
                        }
                        
                        $tempArray[$key] = $mapData;
                    } else if(get_class($value) === "Cassandra\Set") {
                        $mapData = array();
                        foreach ($value as $subKey => $subVal){
                            $mapData[$subKey] = $subVal;
                        }
                        $stringCsv = join(',', $mapData);
                        $tempArray[$key] = $stringCsv;
                    }
                }
                else if(is_null($value)){
                    $tempArray[$key] = "";

                    if($key === 'tags')
                        $tempArray[$key] = new \stdClass();
                }
                else {
                    $tempArray[$key] = $value;
                }
            }
            $rdata[] = $tempArray;
        }
        return $rdata;
    }

    public function insertDataToCassandra($cassandraSession, $tableName, $inputArray){
        $insertStatement = 'INSERT into %s ( %s ) values (%s)';
        
        foreach ($inputArray as $key => $value) {
            $valueType = gettype($value);
            if($valueType == 'string') {
                $value = trim($value, '"');
                $value = str_replace("'","''",$value);
                $inputArray[$key] = "'$value'";

                if($key == "user_id") {
                    $inputArray[$key] = trim($value, "'");
                }
            }
        }
        
        $arrayKeysCSV = implode(',',array_keys($inputArray));
        $arrayValuesCSV = implode(',',array_values($inputArray));

        $finalPreparedStatementTxt = sprintf($insertStatement, $tableName, $arrayKeysCSV, $arrayValuesCSV);
        
        $result = $cassandraSession->execute($finalPreparedStatementTxt);
        return $result;
    }

    public function updateDataToCassandra($cassandraSession, $tableName, $inputArray){
        $insertStatement = 'INSERT into %s ( %s ) values (%s)';

        foreach ($inputArray as $key => $value) {
            $valueType = gettype($value);
            if($valueType == 'string') {
                $value = trim($value, '"');
                $value = str_replace("'","''",$value);
                $inputArray[$key] = "'$value'";

                if($key == "user_id") {
                    $inputArray[$key] = trim($value, "'");
                }
            }
        }

        $arrayKeysCSV = implode(',',array_keys($inputArray));
        $arrayValuesCSV = implode(',',array_values($inputArray));

        $finalPreparedStatementTxt = sprintf($insertStatement, $tableName, $arrayKeysCSV, $arrayValuesCSV);

        $result = $cassandraSession->execute($finalPreparedStatementTxt);
        return $result;
    }
    
    public function convertDataFormatOfInputArray($inputArray){
        foreach ($inputArray as $key => $value) {
            if(gettype($value) === "string") {
                // $inputArray[$key] = substr($inputArray[$key], 1, -1);
            }
            if(gettype($value) === "object") {
                if(get_class($value) === "Cassandra\Uuid") {
                    $inputArray[$key] = (String)$value;
                } else if(get_class($value) === "Cassandra\Timeuuid") {
                    $inputArray[$key] = (String)$value;
                } else if(get_class($value) === "Cassandra\Timestamp") {
                    $inputArray[$key] = (String)$value;
                } else if(get_class($value) === "Cassandra\Float") {
                    $inputArray[$key] = (String)$value;
                } else if(get_class($value) === "Cassandra\Bigint") {
                    $inputArray[$key] = (String)$value;
                }else if(get_class($value) === "Cassandra\Map") {
                    $mapData = array();
                    foreach ($value as $subKey => $subVal){
                        $mapData[$subKey] = $subVal;
                    }
                    $inputArray[$key] = $mapData;
                } else if(get_class($value) === "Cassandra\Set") {
                    $mapData = array();
                    foreach ($value as $subKey => $subVal){
                        $mapData[$subKey] = $subVal;
                    }
                    $stringCsv = join(', ', $mapData);
                    $inputArray[$key] = $stringCsv;
                }
            } 
            
        }
        return $inputArray;
    }
   
    /**
     * Get admin of a channel
     *
     * @param $cassandraSession
     * @param $channelId
     * @return string
     */
    public  function getAdminsForChannel($cassandraSession, $channelId, $getAllAdmin=false, $returnType='array'){
        $cqlStatement = "select user_id from user_by_channel where channel_id=%s and access_level=1;";
        $finalCqlStatement = sprintf($cqlStatement, $channelId);
        $result = $cassandraSession->execute($finalCqlStatement);
        $formattedResult = $this->changeDataFormat($result);

        $rdata = array();
        if($result->count() >0) {
            if($returnType == 'array') {
                foreach ($formattedResult as $row) {
                    if(!$getAllAdmin)
                        $rdata['user_id'] = $row['user_id'];
                    else {
                        $rdata[] = $row;
                    }
                }
            }
            else if($returnType == 'count') {
                $rdata = $result->count();
            }
        }

        return $rdata;
    }
    
    public  function getCushyForChannel($cassandraSession,$channelId,$offset,$limit){
        $getCushyCqlStatement = "select * from cushy_by_channel where channel_id=%s limit %s;";
        $finalGetCushyCqlStatement = sprintf($getCushyCqlStatement, $channelId,$limit);
        $result = $cassandraSession->execute($finalGetCushyCqlStatement);
        $rdata = $this->changeDataFormat($result); 
        return  $rdata;
    }
    
    public function getMemberCountForChannel($cassandraSession,$channelId){
        // select count(*) from user_by_channel where channel_id=80e086fe-267c-11e8-8df1-38c9865b8b61 and access_level < 3;
        $getMembersCountCqlStatement = "select count(*) from user_by_channel where channel_id=%s and access_level < 3";
        $finalGetMembersCountCqlStatement = sprintf($getMembersCountCqlStatement, $channelId);
        
        $result = $cassandraSession->execute($finalGetMembersCountCqlStatement);
        return $result;
    }
    
    public function populateMemberCountForChannel($cassandraSession,$resultArray){
        foreach ($resultArray as $key => $row){
            $channelId = $row['channel_id'];
            $memberCount = $this->getMemberCountForChannel($cassandraSession, $channelId);
            $resultArray[$key]['member_count'] = (int)((array)$memberCount->first())['count'];
        }
        return $resultArray;
    }
    
    public function populateChannelDetails($cassandraSession,$cacheModel,$resultArray){
        foreach ($resultArray as $key => $row){
            $channelId = $row['channel_id'];
            if(isset($resultArray[$key]['is_super_admin'])) unset($resultArray[$key]['is_super_admin']);
            $channelInfoObject = $cacheModel->getChannelInfoObject($channelId);
            $resultArray[$key]['member_count'] = $channelInfoObject['member_count'];
            $resultArray[$key]['channel_desc'] = $channelInfoObject['channel_desc'];
            $resultArray[$key]['channel_dp'] = $channelInfoObject['channel_dp'];
            $resultArray[$key]['channel_name'] = $channelInfoObject['channel_name'];
            $resultArray[$key]['channel_privacy_type'] = $channelInfoObject['channel_privacy_type'];
            $resultArray[$key]['channel_type'] = $channelInfoObject['channel_type'];
        }
        return $resultArray;
    }
    
    public function populateChannelAndUserInformationForCushy($cassandraSession,$resultArray,$cache,$authUser,$cacheModel,$cushyInfoParams=array()){
        $returnArray = array();
        $common = new \app\Models\Common();
        foreach ($resultArray as $key => $cushy) {
            $cushyId = $cushy['cushy_id'];
            $cushyInfo = $cacheModel->getCushyInfoObject($cushyId, $cushyInfoParams);
            //showChildCount==ture Then show the Child Count in Response
            if(isset($cushyInfoParams['showChildCount']) && $cushyInfoParams['showChildCount']){
               $resultArray[$key]['child_cushy_count'] = (isset($cushyInfo['child_cushy_count'])) ? $cushyInfo['child_cushy_count'] :1;
            }
            $resultArray[$key]['category'] = (isset($cushyInfo['category']) AND !empty($cushyInfo['category'])) ? $cushyInfo['category'] : "";
            $resultArray[$key]['is_business'] = (isset($cushyInfo['is_business']) AND !is_null($cushyInfo['is_business'])) ? (int)$cushyInfo['is_business'] : 0;
            $resultArray[$key]['is_published'] = (isset($cushyInfo['is_published']) AND (string)$cushyInfo['is_published'] != '') ? $cushyInfo['is_published'] : 1;
            $resultArray[$key]['total_channels_shared'] = (isset($cushyInfo['total_channels_shared'])) ? $cushyInfo['total_channels_shared'] :0;
            $resultArray[$key]['description'] = $cushyInfo['description'];
            $resultArray[$key]['tags_csv'] = $cushyInfo['tags_csv'];
            $resultArray[$key]['city'] = $cushyInfo['city'];
            $resultArray[$key]['country'] = $cushyInfo['country'];
            $resultArray[$key]['state'] = $cushyInfo['state'];
            $resultArray[$key]['street_name'] = (isset($cushyInfo['street_name']) AND !empty($cushyInfo['street_name'])) ? $cushyInfo['street_name'] : "";
            $resultArray[$key]['sub_locality'] = (isset($cushyInfo['sub_locality']) AND !empty($cushyInfo['sub_locality'])) ? $cushyInfo['sub_locality'] : "";
            
            $channelID = (isset($cushyInfo['first_channel_id'])) ? $cushyInfo['first_channel_id'] : 0;
            
            /*
            if(empty($channelID)){
                unset($resultArray[$key]);
                continue;
            }
            */
            
            $channelInfo = $cacheModel->getChannelInfoObject($channelID);
            if (sizeof($channelInfo) >0) {
                $resultArray[$key]['channel_id'] = $channelInfo['channel_id'];
                //$resultArray[$key]['channel_type'] = $channelInfo['channel_type'];
                $resultArray[$key]['channel_type'] = (!empty($channelInfo['channel_type'])) ? $channelInfo['channel_type'] : 0;
                $resultArray[$key]['channel_name'] = (!empty($channelInfo['channel_name'])) ? $channelInfo['channel_name'] : "";
                $resultArray[$key]['channel_dp'] = (!empty($channelInfo['channel_dp'])) ? $channelInfo['channel_dp'] : "";
                //$resultArray[$key]['channel_privacy_type'] = (!empty($channelInfo['channel_privacy_type'])) ? $channelInfo['channel_privacy_type'] : "oooo";
                $resultArray[$key]['channel_privacy_type'] = $channelInfo['channel_privacy_type'];
                $resultArray[$key]['member_count'] = (!empty($channelInfo['member_count'])) ? $channelInfo['member_count'] : 0;
            }
            else {
                $resultArray[$key]['channel_id'] = $resultArray[$key]['channel_name'] = $channelInfo['channel_dp'] =  '';
                $resultArray[$key]['channel_type'] = 2;
                $resultArray[$key]['channel_privacy_type'] = 1;
                $resultArray[$key]['member_count'] = 0;
            }
            
            $amIFollowing = $cacheModel->amIFollowingUser($authUser,$cushy['user_id']);
            $resultArray[$key]['user_am_i_following'] = $amIFollowing;
            
            $accessLevel = $cacheModel->getUserCahnnelAccessLevel($authUser,$channelID);
            $resultArray[$key]['access_level'] = (!empty($accessLevel)) ? $accessLevel : 4;

            $resultArray[$key]['is_wishlist'] = (!is_null($cushyInfo['is_wishlist'])) ? $cushyInfo['is_wishlist'] : 0;
            $resultArray[$key]['view_count'] = (!is_null($cushyInfo['view_count'])) ? $cushyInfo['view_count'] : 0;
            $resultArray[$key]['bookmark_count'] = (!is_null($cushyInfo['bookmark_count'])) ? $cushyInfo['bookmark_count'] : 0;
            $resultArray[$key]['navigation_count'] = (!is_null($cushyInfo['navigation_count'])) ? $cushyInfo['navigation_count'] : 0;

            $resultArray[$key]['like_count'] = $cushyInfo['like_count'];
            $resultArray[$key]['is_like'] = $cushyInfo['is_like'];
            $resultArray[$key]['comment_count'] = $cushyInfo['comment_count'];
            
            $resultArray[$key]['latest_like_by_user_id'] = $cushyInfo['latest_like_by_user_id'];
            $resultArray[$key]['latest_like_by_user_name'] = $cushyInfo['latest_like_by_user_name'];
            
            $cushyCreatorUserId = $cushyInfo['user_id'];
            $cushyCreatorInfoObject = $cacheModel->getUserInfoObject($cushyCreatorUserId);

            $resultArray[$key]['full_name'] = $common->fullNameToFirstName($cushyCreatorInfoObject['full_name']);
            $resultArray[$key]['user_name'] = $cushyCreatorInfoObject['user_name'];
            $resultArray[$key]['profile_image'] = $cushyCreatorInfoObject['profile_image'];

            $resultArray[$key]['valid_start'] = (isset($cushyInfo['valid_start'])) ? (int)$cushyInfo['valid_start'] : 0;
            $resultArray[$key]['valid_till'] = (isset($cushyInfo['valid_till'])) ? (int)$cushyInfo['valid_till'] : 0;

            $resultArray[$key]['media'] = $cushyInfo['media'];

            $resultArray[$key]['is_cod'] = 0;

            // Add media label and color
            if (!empty($cushyInfo['business_id']) AND $cushyInfo['is_business'] == 1 AND sizeof($resultArray[$key]['media']) >0 AND !empty($cushyInfo['offer_label'])) {
                foreach ($resultArray[$key]['media'] as $k => $media) {
                    $resultArray[$key]['media'][$k]['media_label'] = (isset($cushyInfo['offer_label'][ $media['media_id'] ])) ? $cushyInfo['offer_label'][ $media['media_id'] ] : '';
                    $resultArray[$key]['media'][$k]['media_label_color'] = (isset($cushyInfo['offer_label_color'][ $media['media_id'] ])) ? $cushyInfo['offer_label_color'][ $media['media_id'] ] : '';
                }
            }

            $resultArray[$key]['business_cushy'] = $cushyInfo['business_cushy'];
            
            // Not cached data for business profile addedd@ajaz
            if($cushyInfo['is_business'] == 1 && !empty($cushyInfo['business_cushy']['business_id']) && !empty($cushyInfo['business_cushy']['branch_id'])){
                $businessModel = new \app\Models\BusinessModel($this->container);
                $businessID = $cushyInfo['business_cushy']['business_id'];
                $branchID = $cushyInfo['business_cushy']['branch_id'];
                $resultArray[$key]['business_cushy']['is_user_following_branch'] = $businessModel->isBusinessBranchUserfollow($businessID, $branchID, $this->container->auth_user);
                $resultArray[$key]['business_cushy']['is_user_like_branch'] = $businessModel->isBusinessBranchUserLike($businessID, $branchID, $this->container->auth_user);
                
                if ($this->container->route_name == "branch_profile_by_id") {
                    $getDefultCushy = $businessModel->getBranchInfo($businessID, $branchID);
                    $defualt_branch_cushy_id = $getDefultCushy['defualt_branch_cushy_id'];
                    $mediaImages = $cacheModel->getCushyMediaInfoObject($defualt_branch_cushy_id);
                    $resultArray[$key]['business_cushy']['branch_media_info'] = $mediaImages;
                    $resultArray[$key]['business_cushy']['branch_image'] = $mediaImages[0]['media_medium_url'];
                }
                
            }

            
            $firstChannelInfo = $cacheModel->getChannelInfoObject($cushyInfo['first_channel_id']);
            $resultArray[$key]['share_url'] = $this->getCushyChannelShareUrl($firstChannelInfo['share_code'], $cushyInfo['share_code']);
            
            // Unset not required fields
            unset($resultArray[$key]['business_id'], $resultArray[$key]['branch_id'], $resultArray[$key]['valid_start'], $resultArray[$key]['valid_till'], 
            $resultArray[$key]['business_advert_type'], $resultArray[$key]['offer_branch_count'], $resultArray[$key]['offer_id'], $resultArray[$key]['offer_status'],
            $resultArray[$key]['offer_label'], $resultArray[$key]['offer_label_color'], $resultArray[$key]['is_business']);
            
            if ($cushyInfoParams['remove_unwanted_fields'] == 1) {
                $removefields = array("cod_notification_body","cod_notification_media_url",'cod_notification_notes',
                                      'cod_notification_status','cod_notification_time','cod_notification_title','create_cushy_notification_sent_status',
                                      'cushy_captured_microtime','cushy_captured_time','cushy_hidden_by_users','is_public','labels','latitude',
                                      'location_id','locationlabels','longitude','media_orientation','media_original_height','media_original_url',
                                      'media_original_width','media_type','media_url_medium','media_url_small','offer_display_start','offer_display_till',
                                      'rp_points_allocatted','search_city','share_code','show_in_feed','updated_at','url','total_channels_shared','access_level','is_cod');
                                      
                
                $resultArray[$key]=$common->removeUnwantedFields($resultArray[$key],$removefields);
            }
            //$cushyItem[] = $resultArray[$key];
            
            $cushyItem = new ArrayObject($resultArray[$key]);
            
            if(isset($channelInfo['channel_type']) AND $channelInfo['channel_type'] == 1) {
                $channelUserRow = $this->getUserChannelRow($cassandraSession, $authUser, $channelInfo['channel_id']);
                if(!empty($channelUserRow['other_user_id'])) {
                    $otherUserID = $channelUserRow['other_user_id'];
                    $otherUserInfoObject = $cacheModel->getUserInfoObject($otherUserID);
                    $cushyItem['channel_dp'] = $otherUserInfoObject['profile_image'];
                    $cushyItem['channel_name'] = $otherUserInfoObject['user_name'];
                }
            }
            
            $returnArray[] = $cushyItem;
            
        }
        return $returnArray;
    }
    
    public function getTotalCushysForThisLocation($cassandraSession, $locationIdTxt){
        $count=1;
        $query= 'SELECT COUNT(*) FROM cushy WHERE expr( cushy_location_index, \'{
                            filter : {
                                type: "boolean",
                                must: [
                                       {type: "match", field: "is_public", value: "1"},
                                       {type: "match", field: "location_id_txt", value: "%s"}
                                ]
                            }
            
            
                    }\')';
        $statement = sprintf($query,$locationIdTxt);
        $result = $cassandraSession->execute($statement);
        if($result->count()) {
            $countInfo = (array)$result->first();
            $count = (int)$countInfo['count'];
        }
        return $count;
    }

    /**
     * Media upload
     */
    public function mediaUploadToCDN($container, $mediaPath, $remote_file){
        if(!empty($mediaPath)) {
            $settings = $container->get('settings');
            $api_settings = $settings['API_SETTINGS'];

            $cdn_ftp_server = $api_settings['CDN_FTP_SERVER'];
            $cdn_ftp_user_name = $api_settings['CDN_FTP_USER_NAME'];
            $cdn_ftp_user_pass = $api_settings['CDN_FTP_USER_PASS'];
            $port = 21;
            $timeout = 10;
            $zone_name = $api_settings['CDN_ZONE'];
            $zone_base_url = "http://".$zone_name."-".$api_settings['CDN_ZONE_BASE_URL']."/";

            return $this->uploadFTP($cdn_ftp_server, $cdn_ftp_user_name, $cdn_ftp_user_pass, $port, $timeout, $zone_name, $zone_base_url, $mediaPath, $remote_file);

        }
        return false;
    }

    /**
     * Connect FTP server for image export to CDN
     *
     * @param $server
     * @param $username
     * @param $password
     * @param $port
     * @param $timeout
     * @param $zone_name
     * @param $zone_base_url
     * @param $local_file
     * @param $remote_file
     * @return bool|string
     */
    public function uploadFTP($server, $username, $password, $port, $timeout, $zone_name, $zone_base_url, $local_file, $remote_file){
        // connect to server
        $conn_id = ftp_connect($server, $port, $timeout) or die("Couldn't connect to $server");
        $login_result = ftp_login($conn_id, $username, $password);
        ftp_pasv($conn_id, true);
        ftp_chdir($conn_id, $zone_name);

        // login
        if ($login_result){
            #echo "successfully connected";
        }else{
            return false;
        }

        $ret = ftp_nb_put($conn_id, $remote_file, $local_file, FTP_BINARY);

        while ($ret == FTP_MOREDATA) {
            $ret = ftp_nb_continue($conn_id);
        }

        if($ret != FTP_FINISHED) {
            echo "There was an error uploading the file...<br />";
            exit();
        }

        $link = $zone_base_url.$remote_file;

        ftp_close($conn_id);

        return $link;
    }
    
    // TO be deleted after development
    public function populateChannelAndUserInformationForCushy_BKP($cassandraSession,$resultArray,$cache,$authUser){
        foreach ($resultArray as $key => $row){
            $cushyId = $row['cushy_id'];
            $cacheKey = 'CUSHY_CHANNEL_INFO_'.$cushyId;
            $jsonObject = $cache->get($cacheKey);
            if($jsonObject == null) {
                $objArray = array();
                $statement = 'select * from cushy_by_public_channel where cushy_id=%s';
                $finalStatement = sprintf($statement, $cushyId);
                $result = $cassandraSession->execute($finalStatement);
                if($result->count()){
                    $totalChannels = $result->count();
                    $firstRow = (array)$result->first();
                    $channelId = $firstRow['channel_id'];
                    
                    $channelInfoStmt = 'select * from channel where channel_id=%s';
                    $finalChannelInfoStmt = sprintf($channelInfoStmt,$channelId);
                    $channelResults = $cassandraSession->execute($finalChannelInfoStmt);
                    if($channelResults->count()){
                        $firstChannel = (array)$channelResults->first();
                        
                        /*
                         $resultArray[$key]['group_count'] = $totalChannels;
                         $resultArray[$key]['channel_name'] = $firstChannel['channel_name'];
                         $resultArray[$key]['channel_dp'] = $firstChannel['channel_dp'];
                         $resultArray[$key]['channel_privacy_type'] = $firstChannel['channel_privacy_type'];
                         */
                        
                        $objArray['total_channels_shared'] = $totalChannels;
                        $objArray['channel_name'] = $firstChannel['channel_name'];
                        $objArray['channel_dp'] = $firstChannel['channel_dp'];
                        $objArray['channel_privacy_type'] = $firstChannel['channel_privacy_type'];
                        $objArray['channel_id'] = (string)$firstChannel['channel_id'];
                        
                    }
                    
                    $memberCount = $this->getMemberCountForChannel($cassandraSession, $channelId);
                    //$resultArray[$key]['member_count'] = (int)((array)$memberCount->first())['count'];
                    $objArray['member_count'] = (int)((array)$memberCount->first())['count'];
                    
                    // Populate am_i_following
                    //select * from user_follower where follower_id=4761b096-205b-11e8-bf6c-38c9865b8b61 and user_id=476126bc-205b-11e8-bf6c-38c9865b8b61;
                    $amIFollowingStmt = "select * from user_follower_by_status where follower_id=%s and user_id=%s and status=1";
                    $finalAmIFollowingStmt = sprintf($amIFollowingStmt,$authUser,$row['user_id']);
                    $amIFololowing = $cassandraSession->execute($finalAmIFollowingStmt);
                    if($amIFololowing->count() > 0){
                        $objArray['user_am_i_following'] = 1;
                    } else {
                        $objArray['user_am_i_following'] = 0;
                    }
                    
                    // $resultArray = array_merge($resultArray[$key],$objArray);
                    $jsonObject = json_encode($objArray);
                    $cache->set($cacheKey,$jsonObject);
                }
            }
            
            $objArray = json_decode($jsonObject,true);
            
            $resultArray[$key]['total_channels_shared'] = $objArray['total_channels_shared'];
            $resultArray[$key]['channel_name'] = $objArray['channel_name'];
            $resultArray[$key]['channel_dp'] = $objArray['channel_dp'];
            $resultArray[$key]['channel_privacy_type'] = $objArray['channel_privacy_type'];
            $resultArray[$key]['member_count'] = $objArray['member_count'];
            $resultArray[$key]['user_am_i_following'] = $objArray['user_am_i_following'];
            
            // Populate user access level for the channel
            $channelId = $objArray['channel_id'];
            $accessLevelStmt = "select access_level from channel_user where channel_id=%s and user_id=%s";
            $finalAccessLevelStmt = sprintf($accessLevelStmt,$channelId,$authUser);
            $accessResults = $cassandraSession->execute($finalAccessLevelStmt);
            if($accessResults->count()){
                $firstChannel = (array)$accessResults->first();
                $resultArray[$key]['access_level'] = $firstChannel['access_level'];
            } else {
                $resultArray[$key]['access_level'] = 4;
            }
            
        }
        return $resultArray;
    }

    
    public function insertOrUpdateData($cassandraSession,$tableName,$inputArray,$consistency=Cassandra::CONSISTENCY_QUORUM) {
        $insertStatement = 'INSERT into %s ( %s ) values (%s)';
        $coreKeysArray = array('user_id', 'follower_id', 'channel_id', 'cushy_id', 'recommended_by', 'other_user_id', 'reference_id', 'notification_id', 'log_id', 'location_id', 'media_id', 'referred_by_user_id', 'branch_id', 'business_id');
        foreach ($inputArray as $key => $value) {
            $valueType = gettype($value);
            #echo $value."---".$valueType."\n";
            if(!in_array($key, $coreKeysArray) AND $valueType == 'string' AND $key !== 'tags' AND $key !== 'category' AND $key !== 'interest' AND $key !== 'report_reason') {
                $value = str_replace("'","''",$value);

                $inputArray[$key] = "'$value'";
            } else if($key === 'tags' OR $key === 'report_reason'){
                $isTagMap = false;
                $tagString = str_replace('"', "'", $value);

                $startSubString = substr($tagString, 0, 1);
                $endSubString = substr($tagString, -1, 1);
                if($startSubString === "{" && $endSubString === "}") {
                    $isTagMap = true;
                }

                if ($isTagMap === false && preg_match('#^(\').+\1$#', $tagString) == 0 && preg_match('#^({).+\1#', $tagString) == 0) {
                    $tagString = "'$tagString'";
                }

                $inputArray[$key] = trim($tagString, ",");
            } else if($key === 'category' OR $key === 'interest'){
                $categoryArray = str_getcsv($value);
                $valueString = '';
                foreach ($categoryArray as $categoryKey => $categoryValue) {
                    $categoryArray[$categoryKey] = "'$categoryValue'";
                }
                // Encloses as tag with '
                $stringCsv = join(', ', $categoryArray);
                $setString = "{".$stringCsv."}";
                $inputArray[$key] = $setString;
            } else if($valueType == 'array'){
                $jsonString = json_encode($inputArray[$key]);
                $jsonString = str_replace('"',"'",$jsonString);
                $inputArray[$key] = $jsonString;
            }
        }

        $arrayKeysCSV = implode(',',array_keys($inputArray));
        $arrayValuesCSV = implode(',',array_values($inputArray));
        
        $finalPreparedStatementTxt = sprintf($insertStatement, $tableName, $arrayKeysCSV, $arrayValuesCSV);
        #echo $finalPreparedState
        
        $options = array('consistency' => $consistency);
        $result = $cassandraSession->execute($finalPreparedStatementTxt,$options);
        
        return $result;
    }

    public function deleteDataColumn($cassandraSession,$tableName,$inputArray) {
        $insertStatement = 'DELETE FROM %s WHERE %s';
        $whereClause = "";
        foreach ($inputArray as $key => $value) {
            $whereClause .= $key ."=". $value . " AND ";
        }

        $finalPreparedStatementTxt = sprintf($insertStatement, $tableName, trim($whereClause, " AND "));
        $options = array('consistency' => Cassandra::CONSISTENCY_QUORUM);
        $result = $cassandraSession->execute($finalPreparedStatementTxt,$options);

        return $result;
    }

    public function getTimestamp() {
        $utc_date = new \DateTime(date('Y-m-d H:i:s', time()), new \DateTimeZone('UTC'));
        return $utc_date->format('Y-m-d H:i:s');
    }
    
    public function uploadBase64ImageToS3($fileName,$base64String,$settings,$s3) {
        $s3Result = null;
        $returnUrl = null;
        
        $baseSettings = $settings['BASE_SETTINGS'];
        $amazonS3Settings = $settings['AMAZON_S3'];
        
        $exploded_image = explode(',', $base64String);
        $base64Image = base64_decode($exploded_image[1]);
        
        $fullFileUploadPath = $baseSettings['MEDIA_PATH'].$fileName;
        
        $fhandle = fopen($fullFileUploadPath, "w+");
        fwrite($fhandle, $base64Image);
        fclose($fhandle);

        $keyName = basename($fullFileUploadPath);
        try {
            // Put on S3
            $s3Result = $s3->putObject(
                array (
                    'Bucket'=>$amazonS3Settings['BUCKET_NAME'],
                    'Key' =>  $keyName,
                    'SourceFile' => $fullFileUploadPath,
                    'StorageClass' => 'REDUCED_REDUNDANCY'
                )
            );
        } catch (S3Exception $e) {
            echo $e->getMessage();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        
        if($s3Result != null){
            $returnUrl = $s3Result['ObjectURL'];
        }
        
        return $returnUrl;
    }

    /**
     * Get the list of user device fcm key by user id
     *
     * @param $cassandraSession
     * @param $userId
     * @return array
     */
    public function getUserDeviceByUserId($cassandraSession, $userId){
        $getUserDeviceCqlStatement = "SELECT user_id,device_id,fcm_key,platform,loggedin_status FROM user_device WHERE user_id=%s;";
        $finalGetUserDeviceCqlStatement = sprintf($getUserDeviceCqlStatement, $userId);
        $result = $cassandraSession->execute($finalGetUserDeviceCqlStatement);
        $returnData = $this->changeDataFormat($result);
        if($result->count() >0) {
            foreach ($returnData as $key=> $value) {
                if(!empty($value['device_id']))
                    $returnData[$key]['fcm_key'] = $value['user_id']."||".$value['platform']."||".$value['fcm_key'];
            }
        }

        return $returnData;
    }

    private function checkIfOneToOneChannelRelationExists($cassandraSession,$authUserId,$otherUserId){
        $channelId = '';
        $selectStatement = 'select channel_id from USER_CHANNEL where user_id=%s and other_user_id=%s ALLOW FILTERING';
        $finalCqlStatementTxt = sprintf($selectStatement, $otherUserId,$authUserId);
        $result = $cassandraSession->execute($finalCqlStatementTxt);
        if($result->count() > 0) {
            $returnData = $this->changeDataFormat($result);
            $channelId = $returnData[0]['channel_id'];
            // Insert authuser and otheruserid channel row and return the channel id
            $firstUserChannelRow = array();
            $firstUserChannelRow['channel_type'] = 1;
            $firstUserChannelRow['channel_id'] = $channelId;
            $firstUserChannelRow['user_id'] = new Uuid($authUserId);
            $firstUserChannelRow['other_user_id'] = new Uuid($otherUserId);
            $firstUserChannelRow['access_level'] = 2;
            $firstUserChannelRow['creation_date'] = $this->getTimestamp();
            $firstUserChannelRow['last_modified'] = $this->getTimestamp();
            $firstUserChannelRow['channel_privacy_type'] = 0;
            $this->insertOrUpdateData($cassandraSession, 'USER_CHANNEL', $firstUserChannelRow);
        }
        return $channelId;
    }
    
    private function updateUserAccessLevelAndSendNotification($cassandraSession,$authUserId,$otherUserId,$channelId, $cacheModel){
        $inputArray = array();
        
        $inputArray['channel_id'] = $channelId;
        $inputArray['user_id'] = $authUserId;
        $inputArray['other_user_id'] = $otherUserId;
        $inputArray['access_level'] = 2;
        $inputArray['last_modified'] = $this->getTimestamp();
        $this->insertOrUpdateData($cassandraSession, 'USER_CHANNEL', $inputArray);
        
        $cacheModel->resetUserCahnnelAccessLevel($authUserId, $channelId);
        
        // Update Notification Table
    }
    
    public function getOneToOneChannelId($cassandraSession,$authUserId,$otherUserId,$cacheModel) {
        $otherUserId = trim($otherUserId);
        $selectStatement = 'select channel_id,access_level from USER_CHANNEL where user_id=%s and other_user_id=%s ALLOW FILTERING';
        $finalCqlStatementTxt = sprintf($selectStatement, $authUserId,$otherUserId);
        $result = $cassandraSession->execute($finalCqlStatementTxt);
        if($result->count() > 0) {
            $returnData = $this->changeDataFormat($result);
            $channelId = $returnData[0]['channel_id'];
            if($returnData[0]['access_level'] == 6 ){
                // TODO - Update user access_level = 2 and update notification table
                $this->updateUserAccessLevelAndSendNotification($cassandraSession, $authUserId, $otherUserId,$channelId,$cacheModel);
                $cacheModel->resetUserCahnnelAccessLevel($authUserId, $channelId);
            }
            return $returnData[0]['channel_id'];
        } else {
            
            /*
            $channelId = $this->checkIfOneToOneChannelRelationExists($cassandraSession,$authUserId,$otherUserId);
            if(!empty($channelId)){
                return $channelId;
            }*/
            
            $timeStamp = $this->getTimestamp();
            // Create new channel and add rows to user_channel
            $channelInput = array();
            $channelInput['channel_id'] = new Timeuuid();
            $channelInput['user_id'] = new Uuid($authUserId);
            $channelInput['channel_privacy_type'] = 0;
            $channelInput['channel_type'] = 1;
            $channelInput['creation_date'] = $timeStamp;
            $channelInput['last_modified'] = $timeStamp;
            $channelInput['share_code'] = $this->getUniqueShareURLCode($cassandraSession, 'channel_by_share_code');
            $this->insertOrUpdateData($cassandraSession, 'CHANNEL', $channelInput);
            
            // Insert 2 Rows to user_channel table
            $firstUserChannelRow = array();
            $firstUserChannelRow['channel_type'] = 1;
            $firstUserChannelRow['channel_id'] = $channelInput['channel_id'];
            $firstUserChannelRow['user_id'] = new Uuid($authUserId);
            $firstUserChannelRow['other_user_id'] = new Uuid($otherUserId);
            $firstUserChannelRow['access_level'] = 1;
            $firstUserChannelRow['creation_date'] = $timeStamp;
            $firstUserChannelRow['last_modified'] = $timeStamp;
            $firstUserChannelRow['channel_privacy_type'] = 0;
            $this->insertOrUpdateData($cassandraSession, 'USER_CHANNEL', $firstUserChannelRow);
            
            // Self channel then dont insert second row
            if($authUserId != $otherUserId){
                $secondUserChannelRow = array();
                $secondUserChannelRow['channel_type'] = 1;
                $secondUserChannelRow['channel_id'] = $channelInput['channel_id'];
                $secondUserChannelRow['user_id'] = new Uuid($otherUserId);
                $secondUserChannelRow['other_user_id'] = new Uuid($authUserId);
                $secondUserChannelRow['access_level'] = 6;
                $secondUserChannelRow['creation_date'] = $timeStamp;
                $secondUserChannelRow['last_modified'] = $timeStamp;
                $secondUserChannelRow['channel_privacy_type'] = 0;
                $this->insertOrUpdateData($cassandraSession, 'USER_CHANNEL', $secondUserChannelRow);
            }
            
            return $channelInput['channel_id']->__toString();
        }
    }
    
    public function getUserChannelRow($cassandraSession,$userId,$channelId) {
        $returnRow=array();
        $selectStatement = 'select user_id,channel_id,access_level,other_user_id,recommended_by,channel_type,is_super_admin from user_channel where user_id=%s and channel_id=%s';
        $finalStatement = sprintf($selectStatement,$userId,$channelId);
        $options = array('consistency' => Cassandra::CONSISTENCY_QUORUM);
        $result = $cassandraSession->execute($finalStatement, $options);
        if($result->count() > 0){
            $resultArray = $this->changeDataFormat($result);
            $returnRow = $resultArray[0];
        }
        return $returnRow;
    }
    
    public function updateChannelTable($cassandraSession,$cacheModel,$channelId){
        $channelInput = array();
        $channelInput['channel_id'] = $channelId;
        $channelInput['last_modified'] = $this->getTimestamp();
        $result = $this->insertOrUpdateData($cassandraSession, 'CHANNEL', $channelInput);
        $cacheModel->resetChannelInfoCache($channelId);
    }
    
    public function array_msort($array, $cols)
    {
        $colarr = array();
        foreach ($cols as $col => $order) {
            $colarr[$col] = array();
            foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
        }
        $eval = 'array_multisort(';
        foreach ($cols as $col => $order) {
            $eval .= '$colarr[\''.$col.'\'],'.$order.',';
        }
        $eval = substr($eval,0,-1).');';
        eval($eval);
        $ret = array();
        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                $k = substr($k,1);
                if (!isset($ret[$k])) $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
        }
        return $ret;
        
    }
    
    public function checkIfOneToOneChannelExists($cassandraSession,$authUserId,$otherUserId) {
        $oneToOneChannelId="";
        $selectStatement = 'select channel_id from USER_CHANNEL where user_id=%s and other_user_id=%s ALLOW FILTERING';
        $finalCqlStatementTxt = sprintf($selectStatement, $authUserId,$otherUserId);
        $result = $cassandraSession->execute($finalCqlStatementTxt);
        if($result->count() > 0) {
            $returnData = $this->changeDataFormat($result);
            $oneToOneChannelId = $returnData[0]['channel_id'];
        }
        return $oneToOneChannelId;
    }
    
    public function getUniqueShareURLCode($cassandraSession,$tableName){
        $repeateWhileLoop = 1;
        $returnCode;
        while($repeateWhileLoop) {
            $shareCode = $this->getShareCode(6);
            if(!$this->isShareCodeAssigned($cassandraSession, $tableName, $shareCode)){
                $repeateWhileLoop = 0;
                $returnCode = $shareCode;
            }
        }
        return $returnCode;
    }
    
    private function isShareCodeAssigned($cassandraSession,$tableName,$shareCode) {
        $selectStmt = "select * from %s where share_code='%s'";
        $finalSelectStmt = sprintf($selectStmt, $tableName,$shareCode);
        $result = $cassandraSession->execute($finalSelectStmt);
        if($result->count() > 0) {
            return true;
        }
        return false;
    }
    
    private function getShareCode($length){
        $token = "";
        //$codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet.= "0123456789";
        $max = strlen($codeAlphabet); // edited
        for ($i=0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max-1)];
        }
        return $token;
    }
    
    public function resetCushyPrivacyCodeForShare($cassandraSession, $cacheModel, $cushyId, $userId,$channelInfoArray) {
        
        $cushyInfo = $cacheModel->getCushyInfoObject($cushyId);
        if($cushyInfo['is_public'] == 1){
            return;
        }
        
        // If private is then check if the cushy is shared in any public channel and reset it.
        $cushyPrivacyType = -100;
        foreach ($channelInfoArray as $key => $channelInfoObj) {
            $channelId = $channelInfoObj['channel_id'];
            if($channelInfoObj['channel_privacy_type'] == 0){
                continue;
            } else {
                $cushyPrivacyType = 1;
            }
        }
        
        if($cushyPrivacyType != -100){
            $inputArray = array();
            $inputArray['cushy_id'] = $cushyId;
            $inputArray['user_id'] = $userId;
            $inputArray['is_public'] = $cushyPrivacyType;
            $this->insertOrUpdateData($cassandraSession, 'CUSHY', $inputArray);
        }
        
        /*
         $sqlStatement = 'select * From channel_by_cushy where cushy_id='.$cushyId;
         $result = $cassandraSession->execute($sqlStatement);
         $resultArray = $this->changeDataFormat($result);
         
         $isSharedToPublicChannel = 0;
         $isSharedToPrivateChannel = 0;
         foreach($resultArray as $key => $channel) {
         $channelId = $channel['channel_id'];
         $channelInfo = $cacheModel->getChannelInfoObject($channelId);
         $channelPrivacyType = $channelInfo['channel_privacy_type'];
         if($channelPrivacyType == 0){
         $isSharedToPrivateChannel = 1;
         } else if ($channelPrivacyType == 1){
         $isSharedToPublicChannel = 1;
         }
         }
         $cushyPrivacyType = 0;
         if($isSharedToPrivateChannel == 1 && $isSharedToPublicChannel == 1){
         $cushyPrivacyType = 1;
         } else if($isSharedToPrivateChannel == 1 && $isSharedToPublicChannel == 0){
         $cushyPrivacyType = 0;
         }
         
         
         $inputArray = array();
         $inputArray['cushy_id'] = $cushyId;
         $inputArray['user_id'] = $userId;
         $inputArray['is_public'] = $cushyPrivacyType;
         $this->insertOrUpdateData($cassandraSession, 'CUSHY', $inputArray);
         */
    }
    
    public function resetCushyPrivacyCodeForDelete($cassandraSession, $cacheModel, $cushyId, $userId) {
        $sqlStatement = 'select * From channel_by_cushy where cushy_id='.$cushyId;
        $result = $cassandraSession->execute($sqlStatement);
        $resultArray = $this->changeDataFormat($result);
        
        $isPublic = 0;
        if(count($resultArray) == 0) {
            $isPublic = 0;
        } else {
            foreach($resultArray as $key => $channel) {
                if($channel['channel_privacy_type'] == 1) {
                    $isPublic = 1;
                    break;
                }
            }
        }
        
        /* 
        $isSharedToPublicChannel = 0;
        $isSharedToPrivateChannel = 0;
        foreach($resultArray as $key => $channel) {
            $channelId = $channel['channel_id'];
            $channelInfo = $cacheModel->getChannelInfoObject($channelId);
            $channelPrivacyType = $channelInfo['channel_privacy_type'];
            if($channelPrivacyType == 0){
                $isSharedToPrivateChannel = 1;
            } else if ($channelPrivacyType == 1){
                $isSharedToPublicChannel = 1;
            }
        }
        $cushyPrivacyType = 0;
        if($isSharedToPrivateChannel == 1 && $isSharedToPublicChannel == 1){
            $cushyPrivacyType = 1;
        } else if($isSharedToPrivateChannel == 1 && $isSharedToPublicChannel == 0){
            $cushyPrivacyType = 0;
        }
        */
        
        $inputArray = array();
        $inputArray['cushy_id'] = $cushyId;
        $inputArray['user_id'] = $userId;
        $inputArray['is_public'] = $isPublic;
        $this->insertOrUpdateData($cassandraSession, 'CUSHY', $inputArray);
    }
    
    public function getCushyShareUrl($appendLetter,$shortCode) {
        return $this->BASE_SITE_URL.'/'.$appendLetter.$shortCode;
    }

    public function getStoryWebviewUrl($shortCode) {
        if (!empty($shortCode)) {
            return $this->BASE_SITE_URL.'/app/stories/'.$shortCode.'?cache_string='.time();
        }

        return $shortCode;
    }
    
    public function removeEmojis($text){
        return preg_replace('/[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0077}\x{E006C}\x{E0073}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0073}\x{E0063}\x{E0074}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0065}\x{E006E}\x{E0067}\x{E007F})|[\x{1F3F4}](?:\x{200D}\x{2620}\x{FE0F})|[\x{1F3F3}](?:\x{FE0F}\x{200D}\x{1F308})|[\x{0023}\x{002A}\x{0030}\x{0031}\x{0032}\x{0033}\x{0034}\x{0035}\x{0036}\x{0037}\x{0038}\x{0039}](?:\x{FE0F}\x{20E3})|[\x{1F441}](?:\x{FE0F}\x{200D}\x{1F5E8}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F468})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F468})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B0})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2640}\x{FE0F})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FF}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FE}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FD}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FC}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FB}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F9B8}\x{1F9B9}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FF}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FE}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FD}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FC}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FB}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F9B8}\x{1F9B9}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{200D}\x{2642}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2695}\x{FE0F})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FF})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FE})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FD})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FC})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FB})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FA}](?:\x{1F1FF})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1FA}](?:\x{1F1FE})|[\x{1F1E6}\x{1F1E8}\x{1F1F2}\x{1F1F8}](?:\x{1F1FD})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F9}\x{1F1FF}](?:\x{1F1FC})|[\x{1F1E7}\x{1F1E8}\x{1F1F1}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1FB})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1FB}](?:\x{1F1FA})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FE}](?:\x{1F1F9})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FA}\x{1F1FC}](?:\x{1F1F8})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F7})|[\x{1F1E6}\x{1F1E7}\x{1F1EC}\x{1F1EE}\x{1F1F2}](?:\x{1F1F6})|[\x{1F1E8}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}](?:\x{1F1F5})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EE}\x{1F1EF}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F8}\x{1F1F9}](?:\x{1F1F4})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1F3})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FF}](?:\x{1F1F2})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F1})|[\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FD}](?:\x{1F1F0})|[\x{1F1E7}\x{1F1E9}\x{1F1EB}\x{1F1F8}\x{1F1F9}](?:\x{1F1EF})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EB}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F3}\x{1F1F8}\x{1F1FB}](?:\x{1F1EE})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1ED})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1EC})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F9}\x{1F1FC}](?:\x{1F1EB})|[\x{1F1E6}\x{1F1E7}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FB}\x{1F1FE}](?:\x{1F1EA})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1E9})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FB}](?:\x{1F1E8})|[\x{1F1E7}\x{1F1EC}\x{1F1F1}\x{1F1F8}](?:\x{1F1E7})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F6}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}\x{1F1FF}](?:\x{1F1E6})|[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{270D}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F321}\x{1F324}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F3F0}\x{1F3F3}-\x{1F3F5}\x{1F3F7}-\x{1F3FA}\x{1F400}-\x{1F4FD}\x{1F4FF}-\x{1F53D}\x{1F549}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}-\x{1F57A}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F590}\x{1F595}-\x{1F596}\x{1F5A4}-\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}-\x{1F64F}\x{1F680}-\x{1F6C5}\x{1F6CB}-\x{1F6D2}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}-\x{1F6F9}\x{1F910}-\x{1F93A}\x{1F93C}-\x{1F93E}\x{1F940}-\x{1F945}\x{1F947}-\x{1F970}\x{1F973}-\x{1F976}\x{1F97A}\x{1F97C}-\x{1F9A2}\x{1F9B0}-\x{1F9B9}\x{1F9C0}-\x{1F9C2}\x{1F9D0}-\x{1F9FF}]/u', '', $text);
    }
    
    public function getEscapedCharacterForLuceneSearch($tag){
        $tag = str_replace("\\", "\\\\", $tag);
        $tag = str_replace('"', '\"', $tag);
        $tag = str_replace("'", "''", $tag);
        return $tag;
    }
    
    public function getChannelShareUrl($shortCode){
        return $this->BASE_SITE_URL.'/g/'.$shortCode;
    }
    
    public function getUserShareUrl($userName){
        return $this->BASE_SITE_URL.'/'.$userName;
    }
    
    public function getCushyChannelShareUrl($channelShareCode, $cushyShareCode){
        if(!empty($channelShareCode) AND !empty($cushyShareCode))
            $finalShareUrl = $channelShareCode.'/'.$cushyShareCode;
        else
            $finalShareUrl = $cushyShareCode;

        return $this->BASE_SITE_URL.'/c/'.$finalShareUrl;
    }

    public function getOrphanCushyShareUrl($cushyShareCode){
        return $this->BASE_SITE_URL.'/c/'.$cushyShareCode;
    }
    
    public function getChannelShareCode($cassandraSession, $channelId){
        $shareCode = 'na';
        $selectStatement = 'select channel_id,share_code from channel where channel_id=%s';
        $finalStatement = sprintf($selectStatement,$channelId);
        $result = $cassandraSession->execute($finalStatement);
        if($result->count() > 0){
            $shareCode = $result[0]['share_code'];
        }
        return $shareCode;
    }
    
    public function distanceBetweenTwoPoints($lat1, $lon1, $lat2, $lon2, $unit) {
        
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        $adjustmentValue = 1.4;

        if ($unit == "K") {
            $inKms = ($miles * 1.609344);
            if($inKms < 500 ){
                $adjustmentValue = 1.4;
            } else if($inKms >= 500 && $inKms <= 1000){
                $adjustmentValue = 1.2;
            } else if($inKms >= 500 && $inKms <= 1000){
                $adjustmentValue = 1;
            }
            return $inKms * $adjustmentValue;
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }

    public function getCushyAdditionalInput($input, $additionalInput=array()) {
        $returnInput = array();
        if(!empty($input)) {
            $latitude = $this->getLatitude($input);
            $longitude = $this->getLongitude($input);

            if(empty($latitude) && empty($longitude)) {
                $latitude = isset($additionalInput['latitude']) ? $additionalInput['latitude'] : "";
                $longitude = isset($additionalInput['longitude']) ? $additionalInput['longitude'] : "";
            }

            $returnInput = array('latitude' => $latitude, 'longitude' => $longitude);
        }

        return $returnInput;
    }
    
    public function getCategory (ServerRequestInterface $request) {
        $category = '';
        if($request->getQueryParam('category') != null){
            $category = $request->getQueryParam('category');
        }
        return $category;
    }
    
    public function getCushyLatitude (ServerRequestInterface $request){
        $latitude = '';
        if($request->getQueryParam('cushy_lat') != null){
            $latitude = $request->getQueryParam('cushy_lat');
        }
        return $latitude;
    }
    
    public function getCushyLongitude (ServerRequestInterface $request){
        $longitude = '';
        if($request->getQueryParam('cushy_long') != null){
            $longitude = $request->getQueryParam('cushy_long');
        }
        return $longitude;
    }
    
    public function getUserLatitude (ServerRequestInterface $request){
        $latitude = '';
        if($request->getQueryParam('user_lat') != null){
            $latitude = $request->getQueryParam('user_lat');
        }
        return $latitude;
    }
    
    public function getUserLongitude (ServerRequestInterface $request){
        $longitude = '';
        if($request->getQueryParam('user_long') != null){
            $longitude = $request->getQueryParam('user_long');
        }
        return $longitude;
    }
    
    public function getCushyLocationIdTxt (ServerRequestInterface $request){
        $locationId = '';
        if($request->getQueryParam('cushy_location_id_txt') != null){
            $locationId = $request->getQueryParam('cushy_location_id_txt');
        }
        return $locationId;
    }
    
    public function checkEmpty($value){
        if(!empty($value)){
            return $value;
        }
        return '';
    }
    
    public static function checkIsset($check_var, $index, $default_val = "") {
        return (isset($check_var[$index]) && (!empty($check_var[$index]) || $check_var[$index] == 0)) ? $check_var[$index] : $default_val;
    }
     
     
}