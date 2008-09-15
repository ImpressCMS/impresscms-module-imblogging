<?php
/**
* Classes responsible for managing imBlogging post objects
*
* @copyright	http://smartfactory.ca The SmartFactory
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
* @since		1.0
* @author		marcan aka Marc-André Lanciault <marcan@smartfactory.ca>
* @version		$Id$
*/

if (!defined("ICMS_ROOT_PATH")) die("ICMS root path not defined");

// including the IcmsPersistabelSeoObject
include_once ICMS_ROOT_PATH."/kernel/icmspersistableseoobject.php";

/**
 * Post status definitions
 */
define ('IMBLOGGING_POST_STATUS_PUBLISHED', 1);
define ('IMBLOGGING_POST_STATUS_PENDING', 2);
define ('IMBLOGGING_POST_STATUS_DRAFT', 3);
define ('IMBLOGGING_POST_STATUS_PRIVATE', 4);

class ImbloggingPost extends IcmsPersistableSeoObject {

	/**
	 * Constructor
	 */
    function ImbloggingPost(&$handler) {
    	global $xoopsConfig;

    	$this->IcmsPersistableObject($handler);

        $this->quickInitVar('post_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('post_title', XOBJ_DTYPE_TXTBOX);
        $this->quickInitVar('post_content', XOBJ_DTYPE_TXTAREA);
		$this->quickInitVar('post_published_date', XOBJ_DTYPE_LTIME);
		$this->quickInitVar('post_uid', XOBJ_DTYPE_INT);
		$this->quickInitVar('post_status', XOBJ_DTYPE_INT, false, false, false, IMBLOGGING_POST_STATUS_PUBLISHED);
		$this->quickInitVar('post_cancomment', XOBJ_DTYPE_INT, false, false, false, true);
		$this->quickInitVar('post_comments', XOBJ_DTYPE_INT);
		$this->hideFieldFromForm('post_comments');

		$this->quickInitVar('post_notification_sent', XOBJ_DTYPE_INT);
		$this->hideFieldFromForm('post_notification_sent');

		$this->initCommonVar('counter', false);
		$this->initCommonVar('dohtml', false, true);
		$this->initCommonVar('dobr', false, $xoopsConfig['editor_default'] == 'dhtmltextarea');
		$this->initCommonVar('doimage', false, true);
		$this->initCommonVar('dosmiley', false, true);
		$this->initCommonVar('doxcode', false, true);

		$this->setControl('post_content', 'dhtmltextarea');
		$this->setControl('post_uid', 'user');
		$this->setControl('post_status', array(
											'itemHandler' => 'post',
											'method' => 'getPost_statusArray',
											 'module' => 'imblogging'));

		$this->setControl('post_cancomment', 'yesno');

		$this->IcmsPersistableSeoObject();
    }

    function getVar($key, $format = 's') {
        if ($format == 's' && in_array($key, array('post_uid', 'post_status'))) {
            return call_user_func(array($this,$key));
        }
        return parent::getVar($key, $format);
    }

    function post_uid() {
        return icms_getLinkedUnameFromId($this->getVar('post_uid', 'e'));
    }

    function post_status() {
        $ret = $this->getVar('post_status', 'e');
        $post_statusArray = $this->handler->getPost_statusArray();
        return $post_statusArray[$ret];
    }

    function getPoster($link=false) {
    	$member_handler = xoops_getHandler('member');
    	$poster_uid = $this->getVar('post_uid', 'e');
    	$userObj = $member_handler->getuser($poster_uid);

		if ($link) {
			return '<a href="' . IMBLOGGING_URL . 'index.php?uid=' . $poster_uid . '">' . $userObj->getVar('uname') . '</a>';
		} else {
			return $userObj->getVar('uname');
		}
    }

    function getPostInfo() {
		$ret = sprintf(_CO_IMBLOGGING_POST_INFO, $this->getPoster(true), $this->getVar('post_published_date'));
		return $ret;
    }

    function getCommentsInfo() {
    	$post_comments = $this->getVar('post_comments');
		if ($post_comments) {
			return '<a href="' . $this->getItemLink(true) . '#comments_container">' . sprintf(_CO_IMBLOGGING_POST_COMMENTS_INFO, $post_comments) . '</a>';
		} else {
			return _CO_IMBLOGGING_POST_NO_COMMENT;
		}
    }

    function getPostLead() {
    	$ret = $this->getVar('post_content');
    	$slices = explode('[more]', $ret);
    	return $slices[0];
    }

    function sendNotifPostPublished() {
    	global $imbloggingModule;
    	$module_id = $imbloggingModule->getVar('mid');
		$notification_handler = xoops_getHandler('notification');

		$tags['POST_TITLE'] = $this->getVar('post_title');
		$tags['POST_URL'] = $this->getItemLink(true);

		$notification_handler->triggerEvent('global', 0, 'post_published', $tags, array(), $module_id);
    }

    function toArray() {
		$ret = parent::toArray();
		$ret['post_info'] = $this->getPostInfo();
		$ret['post_lead'] = $this->getPostLead();
		$ret['post_comment_info'] = $this->getCommentsInfo();
		return $ret;
    }

}
class ImbloggingPostHandler extends IcmsPersistableObjectHandler {

    /**
     * @var array of status
     */
    var $_post_statusArray = array();

	/**
	 * Constructor
	 */
    function ImbloggingPostHandler($db) {
        $this->IcmsPersistableObjectHandler($db, 'post', 'post_id', 'post_title', '', 'imblogging');
    }

	/**
	 * Retreive the possible status of a post object
	 *
	 * @return array of status
	 */
    function getPost_statusArray() {
	    if (!$this->_post_statusArray) {
			$this->_post_statusArray[IMBLOGGING_POST_STATUS_PUBLISHED] = _CO_IMBLOGGING_POST_STATUS_PUBLISHED;
			$this->_post_statusArray[IMBLOGGING_POST_STATUS_PENDING] = _CO_IMBLOGGING_POST_STATUS_PENDING;
			$this->_post_statusArray[IMBLOGGING_POST_STATUS_DRAFT] = _CO_IMBLOGGING_POST_STATUS_DRAFT;
			$this->_post_statusArray[IMBLOGGING_POST_STATUS_PRIVATE] = _CO_IMBLOGGING_POST_STATUS_PRIVATE;
	    }
	    return $this->_post_statusArray;
    }

    /**
     * Get posts as array, ordered by post_published_date DESC
     *
     * @param int $post_uid if specifid, only the post of this user will be returned
     * @return array of posts
     */
    function getPosts($post_uid = false) {
    	$criteria = new CriteriaCompo();
    	$criteria->setSort('post_published_date');
    	$criteria->setOrder('DESC');
    	$criteria->add(new Criteria('post_status', IMBLOGGING_POST_STATUS_PUBLISHED));
    	if ($post_uid) {
    		$criteria->add(new Criteria('post_uid', $post_uid));
    	}
    	$ret = $this->getObjects($criteria, true, false);
    	return $ret;
    }

    function getPostsForSearch($queryarray, $andor, $limit, $offset, $userid) {
		$criteria = new CriteriaCompo();

		if ($userid != 0) {
			$criteria->add(new Criteria('post_uid', $userid));
		}
		if ($queryarray) {
			$criteriaKeywords = new CriteriaCompo();
			for ($i = 0; $i < count($queryarray); $i++) {
				$criteriaKeyword = new CriteriaCompo();
				$criteriaKeyword->add(new Criteria('post_title', '%' . $queryarray[$i] . '%', 'LIKE'), 'OR');
				$criteriaKeyword->add(new Criteria('post_content', '%' . $queryarray[$i] . '%', 'LIKE'), 'OR');
				$criteriaKeywords->add($criteriaKeyword, $andor);
				unset($criteriaKeyword);
			}
			$criteria->add($criteriaKeywords);
		}
		$criteria->add(new Criteria('post_status', IMBLOGGING_POST_STATUS_PUBLISHED));
		return $this->getObjects($criteria, true, false);
    }

	/**
	 * Update number of comments on a post
	 *
	 * This method is triggered by imblogging_com_update in include/functions.php which is
	 * called by ImpressCMS when updating comments
	 *
	 * @param int $post_id id of the post to update
	 * @param int $total_num total number of comments so far in this post
	 * @return VOID
	 */
    function updateComments($post_id, $total_num) {
		$postObj = $this->get($post_id);
		if ($postObj && !$postObj->isNew()) {
			$postObj->setVar('post_comments', $total_num);
			$this->insert($postObj, true);
		}
    }

	/**
	 * AfterSave event
	 *
	 * Event automatically triggered by IcmsPersistable Framework after the object is inserted or updated
	 *
	 * @param object $obj ImbloggingPost object
	 * @return true
	 */
    function afterSave(&$obj){
		if (!$obj->getVar('post_notification_sent') && $obj->getVar('post_status', 'e') == IMBLOGGING_POST_STATUS_PUBLISHED) {
			$obj->sendNotifPostPublished();
			$obj->setVar('post_notification_sent', true);
			$this->insert($obj);
		}
		return true;
    }
}
?>