<?php

namespace App\Form;

use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType as TypeTextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CategoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TypeTextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Nom de la catégorie',
                ],
                'constraints' => 
                [new NotBlank([
                    'message' => 'Ce champ ne peut être vide',
                ]),
                new Length([
                    'min' => 3,
                    'max' => 50,
                    'minMessage' => "Votre nom de catégorie est trop court. Le nombre de caractère minimal est de {{ limit }} lettres.",
                    'maxMessage' => "Votre nom de catégorie est trop long. Le nombre de caractère maximal est de {{ limit }} lettres."
                ]),
                ],      
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Categorie::class,
        ]);
    }
}
