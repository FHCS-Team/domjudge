<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Rubric;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RubricType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Rubric Name',
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Rubric Type',
                'choices' => [
                    'Manual' => 'manual',
                    'Automated' => 'automated',
                ],
                'required' => true,
            ])
            ->add('weight', NumberType::class, [
                'label' => 'Weight',
                'required' => true,
            ])
            ->add('threshold', NumberType::class, [
                'label' => 'Threshold',
                'required' => false,
            ])
            ->add('description', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Rubric Description',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rubric::class,
        ]);
    }
}
