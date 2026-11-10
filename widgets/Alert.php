<?php

declare(strict_types=1);

namespace app\widgets;

use Yii;
use yii\bootstrap5\Alert as BootstrapAlert;

class Alert extends \yii\bootstrap5\Widget
{

    public array $alertTypes = [
        'error'   => 'alert-danger',
        'danger'  => 'alert-danger',
        'success' => 'alert-success',
        'info'    => 'alert-info',
        'warning' => 'alert-warning'
    ];

    public array $closeButton = [];

    public function run(): void
    {
        $session = Yii::$app->session;

        if (!$session->getIsActive() && !$session->getHasSessionId()) {
            return;
        }

        $appendClass = isset($this->options['class']) ? ' ' . $this->options['class'] : '';

        foreach (array_keys($this->alertTypes) as $type) {
            $flash = $session->getFlash($type);

            foreach ((array) $flash as $i => $message) {
                echo BootstrapAlert::widget(
                    [
                        'body' => $message,
                        'closeButton' => $this->closeButton,
                        'options' => [
                            ...$this->options,
                            'id' => $this->getId() . '-' . $type . '-' . $i,
                            'class' => $this->alertTypes[$type] . $appendClass
                        ],
                    ],
                );
            }

            $session->removeFlash($type);
        }
    }
}
