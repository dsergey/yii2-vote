<?php
/**
 * @link https://github.com/Chiliec/yii2-vote
 * @author Vladimir Babin <vovababin@gmail.com>
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace chiliec\vote\widgets;

use chiliec\vote\models\Rating;
use chiliec\vote\Module;
use yii\base\InvalidParamException;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\web\View;
use yii\web\JsExpression;
use Yii;

class Vote extends Widget
{
    /**
     * @var ActiveRecord
     */
    public $model;

    /** @var  string */
    public $viewFile;

    /**
     * @var string
     */
    public $voteUrl;

    /**
     * @var bool
     */
    public $showAggregateRating = true;

    /**
     * @var string
     */
    public $jsBeforeVote;

    /**
     * @var string
     */
    public $jsAfterVote;

    /**
     * @var string
     */
    public $jsCodeKey = 'vote';

    /**
     * @var string
     */
    public $jsPopOverErrorVote = "
        $('#vote-' + model + '-' + target).popover({
            content: function() {
               return errorThrown;
            }
        }).popover('show');
    ";

    /**
     * @var string
     */
    public $jsErrorVote = "
        jQuery('#vote-response-' + model + '-' + target).html(errorThrown);
    ";

    /**
     * @var string
     */
    public $jsPopOverShowMessage = "
        $('#vote-' + model + '-' + target).popover({
            content: function() {
               return data.content;
            }
        }).popover('show');
    ";

    /**
     * @var string
     */
    public $jsShowMessage = "
        jQuery('#vote-response-' + model + '-' + target).html(data.content);
    ";

    /**
     * @var string
     */
    public $jsChangeCounters = <<<JS
        if (typeof(data.success) !== 'undefined') {
            var idUp = '#vote-up-' + model + '-' + target;
            var idDown = '#vote-down-' + model + '-' + target;
            if (act === 'like') {
                jQuery(idUp).text(parseInt(jQuery(idUp).text()) + 1);
            } else {
                jQuery(idDown).text(parseInt(jQuery(idDown).text()) + 1);
            }
            if (typeof(data.changed) !== 'undefined') {
                if (act === 'like') {
                    jQuery(idDown).text(parseInt(jQuery(idDown).text()) - 1);
                } else {
                    jQuery(idUp).text(parseInt(jQuery(idUp).text()) - 1);
                }
            }
        }
JS;

    /**
     * @var string
     */
    public $jsPopOver = <<<JS
        $('body').on('click', function (e) {
            $('[data-toggle="popover"]').each(function () {
                if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                    $(this).popover('hide');
                }
            });
        });

        $('[data-toggle="popover"]').click(function (e) {
            setTimeout(function () {
                    $('#'+e.currentTarget.id).popover('hide');
            }, 8000);
        });
JS;

    public function init()
    {
        parent::init();
        if (!isset($this->model)) {
            throw new InvalidParamException(Yii::t('vote', 'Model not configurated'));
        }

        if (!isset($this->voteUrl)) {
            $this->voteUrl = Yii::$app->getUrlManager()->createUrl(['vote/default/vote']);
        }

        $showMessage = $this->jsShowMessage;
        $errorMessage = $this->jsErrorVote;
        if (Yii::$app->getModule('vote')->popOverEnabled) {
            $js2 = new JsExpression($this->jsPopOver);
            $this->view->registerJs($js2, View::POS_END);
            $showMessage = $this->jsPopOverShowMessage;
            $errorMessage = $this->jsPopOverErrorVote;
        }

        $js = new JsExpression("
            function vote(model, target, act) {
                jQuery.ajax({ 
                    url: '$this->voteUrl', type: 'POST', dataType: 'json', cache: false,
                    data: { modelId: model, targetId: target, act: act },
                    beforeSend: function(jqXHR, settings) { $this->jsBeforeVote },
                    success: function(result, textStatus, jqXHR) { 
                        data = result;
                        $this->jsChangeCounters
                        $showMessage
                    },
                    complete: function(jqXHR, textStatus) {
                        $this->jsAfterVote
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        errorMessage
                    }
                });
            }
        ");
         $this->view->registerJs($js, View::POS_END, $this->jsCodeKey);

        $this->afterInit();
    }

    /** @var  int */
    private $_modelId;
    /** @var  int */
    private $_targetId;
    /** @var  Rating */
    private $_rating;

    /**
     * Trying get rating
     */
    public function afterInit()
    {
        $this->_modelId = Rating::getModelIdByName($this->model->className());
        $this->_targetId = $this->model->{$this->model->primaryKey()[0]};

        $userIp = Rating::compressIp(Yii::$app->request->getUserIP());
        $userId = Yii::$app->user->getId();

        if (Rating::getIsAllowGuests($this->_modelId)) {
            $this->_rating = Rating::findOne([
                'model_id' => $this->_modelId,
                'target_id' => $this->_targetId,
                'user_ip' => $userIp]);

        } else {
            $this->_rating = Rating::findOne([
                'model_id' => $this->_modelId,
                'target_id' => $this->_targetId,
                'user_id' => $userId]);
        }

    }

    public function run()
    {
        $viewFile = $this->viewFile ?: 'vote';
        return $this->getView()->render($viewFile, [
            'modelId' => $this->_modelId,
            'targetId' => $this->_targetId,
            'likes' => isset($this->model->aggregate->likes) ? $this->model->aggregate->likes : 0,
            'dislikes' => isset($this->model->aggregate->dislikes) ? $this->model->aggregate->dislikes : 0,
            'rating' => isset($this->model->aggregate->rating) ? $this->model->aggregate->rating : 0.0,
            'showAggregateRating' => $this->showAggregateRating,
            'isVoted' => $this->isVoted(),
            'choice' => $this->getChoice(),
        ]);
    }


    /**
     * @return bool
     */
    private function isVoted()
    {
        return $this->_rating ? true : false;
    }

    /**
     * @return bool|int
     */
    private function getChoice()
    {
        if ($this->_rating) {
            return $this->_rating->value;
        }

        return false;
    }

}
