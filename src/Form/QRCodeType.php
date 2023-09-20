<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QRCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('serial', TextType::class, [
                'label' => 'Серия',
                'row_attr' => [
                    'class' => 'input-group'
                ],
                'attr' => [
                    'placeholder' => "Например, TL...",
                    'value' => 'TL;TR;TA'
                ]
            ])
            ->add('sample', TextType::class, [
                'label' => 'Шаблон',
                'row_attr' => [
                    'class' => 'input-group'
                ],
                'attr' => [
                    'placeholder' => "Например, TL...",
                    'value' => 'http://%s.fld.ru'
                ]
            ])
            ->add('size', TextType::class, [
                'label' => 'Размер',
                'row_attr' => [
                    'class' => 'input-group'
                ],
                'attr' => [
                    'value' => '200',
                    'placeholder' => "Например, TL..."
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Создать',
                'row_attr' => [
                    'class' => 'input-group'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
