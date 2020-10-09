<?php

namespace Alchemy\Phrasea\WorkerManager\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class WorkerPullAssetsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('UploaderApiBaseUri', 'text', [
                'label' => 'admin::workermanager:tab:pullassets: Uploader api base uri'
            ])
            ->add('clientSecret', 'text', [
                'label' => 'admin::workermanager:tab:pullassets: Client secret'
            ])
            ->add('clientId', 'text', [
                'label' => 'admin::workermanager:tab:pullassets: Client ID'
            ])
            ->add('pullInterval', 'text', [
                'label' => 'admin::workermanager:tab:pullassets: Fetching interval in second'
            ])
        ;
    }

    public function getName()
    {
        return 'worker_pullAssets';
    }
}
