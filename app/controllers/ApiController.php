<?php
namespace Souii\Controllers;
use Souii\Models\Article as Article;
use Souii\Models\Category;
use Souii\Models\Tags as Tags;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Paginator\Adapter\Model as Paginator;
use Souii\Redis\RedisUtils as RedisUtils;

/**
 * @RoutePrefix("/api")
 * Class IndexController
 */
class ApiController extends JsonControllerBase
{

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * Index action
     */
    public function indexAction()
    {
        $this->persistent->parameters = null;
    }

    /**
     * @Route("/page={numberpage}/tag={tag}/cate={cate}/blog", methods={"GET"}, name="blogget")
     * @param int $numberPage
     * @return stdclass
     */
    public function bloggetAction($numberpage = 1,$tag='',$cate='')
    {
            return $this->redisUtils->getCache(RedisUtils::$CACHEKEYS['ARTICLE']['PAGE:TAG:CATE'],'Souii\Controllers\ApiController::blogget',$numberpage,$tag,$cate);
    }

    public static function blogget($numberpage,$tag='',$cate=''){
        $parameters = array();
        $parameters["order"] = "created_at desc";
        $parameters['columns'] = array('id,title,tags,cate_id,updated_at');
        $conditions = "1=1";
        $parameter = array();
        if($cate!=''){
            $conditions .= " AND cate_id = :cate_id:";
            $parameter['cate_id']=$cate;
        }
        if($tag!=''){
            $ids = Tags::getIDs($tag,null,false,false);
            $conditions .= " AND tags in (:tags:)";
            $parameter['tags']=$ids;
        }
        $parameters['conditions'] = $conditions;
        $parameters['bind'] = $parameter;

        $article = Article::find($parameters);
        $paginator = new Paginator(array(
            "data" => $article,
            "limit"=> 10,
            "page" => $numberpage
        ));
        $page =          $paginator->getPaginate();
        $map = Tags::getAll();
        $categorysmap = Category::getAll();

        foreach($page->items as $item){
            $ret = [];
            $tags = explode(',',$item->tags);
            foreach($tags as $tag){
                if(!empty($tag)){
                    $ret[] = $map[$tag]['name'];
                }
            }
            $item->cate_name = $categorysmap[$item->cate_id]['cate_name'];
            $item->class_name = $categorysmap[$item->cate_id]['class_name'];
            $item->tags = implode(',',$ret);
        }
        return $page;
    }

    /**
     * @Route("/id={id}/blog", methods={"GET"}, name="bloggetinfo")
     * @param int $id
     * @return stdclass
     */
    public function bloggetinfoAction($id = 1)
    {
        return $this->redisUtils->getCache(RedisUtils::$CACHEKEYS['ARTICLE']['ID'],'Souii\Controllers\ApiController::bloggetinfo',$id);
    }

    public static function bloggetinfo($id)
    {
        $atricle = Article::findFirst(
            array(
                "id = :id:",
                'bind' => array('id' => $id)
            )
        );
        $map = Tags::getAll();
        $ret = [];
        $tags = explode(',', $atricle->tags);
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $ret[] = $map[$tag]['name'];
            }
        }
        $atricle->tags = implode(',', $ret);
        return $atricle;
    }

    /**
     * @Route("/qqlogin", methods={"POST"}, name="qqlogin")
     */
    public function qqloginAction(){
        $token = $this->request->getPost('token',null,'');
        $uid = $this->request->getPost('uid',null,'');
        $name = $this->request->getPost('name',null,'');
        $photo = $this->request->getPost('photo',null,'');
        $user = SessionController::registerUser($uid,Users::USER_SOURCE_QQ,$name,null,$photo);
        $this->setSession('user',array('id'=>$user->id,
            'name'=>$user->name,
            'email'=>$user->email,
            'type'=>$user->type,
            'uid'=>$user->uid,
            'source'=>$user->source));
        $this->setSession('token',$token);
        $this->setSession('tokentype',Users::USER_SOURCE_QQ);
    }

    /**
     * @Route("/endsession", methods={"POST"}, name="endsession")
     * @return bool
     */
    public function  endsessionAction(){
        $this->destorySession();
        return true;
    }

}
