<?php

class IndexAction extends CommonAction {

    public function index(){
        unset($_SESSION["user"]["password"]);

        $auth = new Auth();
        $rules = $auth->getAuthList(getCurrentUid());
//        print_r($rules);exit;

        $data = array(
            "authed" => reIndex($rules),
            "navs" => $this->makeNav(),
            "user" => $_SESSION["user"]
        );

        $this->response($data);
    }

    /**
     * 根据AuthRule生成左侧导航，不同用户生成不同缓存
     * @todo 三级分类（快捷导航）
     */
    private function makeNav() {
        $navs = F("Nav/".$this->user["id"]);
        if($navs) {
            return $navs;
        }
        
        $navs = require APP_PATH."Conf".DS."navs.php";

        $appConf = $this->getAppConfig();
        $navs = array_merge_recursive($navs, $appConf["navs"]);

        import("@.ORG.Auth");

        foreach($navs as $rootLabel => $data) {
            $theChild = array();
            foreach($data["childs"] as $childLabel => $childData) {
                $theThird = array();
                // 包含三级菜单
                if(is_array($childData)) {
                    foreach($childData as $thirdLabel => $thirdData) {
                        if($this->checkNavPermission($thirdData)) {
                            $theThird[] = array(
                                "label" => $thirdLabel,
                                "url"   => $thirdData,
                                "id"    => md5($thirdData.$thirdLabel)
                            );
                        } else {
//                            print_r($thirdData);
                        }
                    }
                }
                
                if($theThird) {
                    $theChild[$childLabel]["childs"] = $theThird;
                } else {
                    $tmpRs = $this->checkNavPermission($childData);
//                    echo $childData;
//                    var_dump($tmpRs);
                    if($tmpRs) {
                        $theChild[$childLabel]["url"] = $childData;
                    }
                }
                if($theThird or $tmpRs) {
                    $theChild[$childLabel]["label"] = $childLabel;
                    $theChild[$childLabel]["id"] = md5($childLabel.json_encode($childData));
                }
                
            }
            $theChild = reIndex($theChild);
            if($theChild or $this->checkNavPermission($data["action"])) {
                $theNav[$rootLabel] = array(
                    "childs" => $theChild,
                    "label"  => $rootLabel,
                    "icon"   => $data["icon"],
                    "id"     => md5($rootLabel.json_encode($data)),
                    "url"    => $data["action"] ? $data["action"] : ""
                );
            }
        }


        $theNav = reIndex($theNav);

        return $theNav;
    }
    
    private function checkNavPermission($url) {
        list($group, $action, $module) = explode("/", $url);
//        var_dump(preg_match("/[A-Z]+.*/", $group));
        //非rest模式， $action和$module对换
        $notRest = preg_match("/^[A-Z]/", $action);
        if($notRest) {
            $tmp = $module;
            $module = $action;
            $action = $tmp ? $tmp : "Index";
        } else {
            $action = $this->parseActionName($action);
            $module = ucfirst($module);
        }
        
        $rule = sprintf("%s.%s.%s", $group, $module, $action);

        $result = $this->checkPermission($rule, true);
        
        return $result;
    }
    
}