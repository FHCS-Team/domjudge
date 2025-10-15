<?php declare(strict_types=1);

namespace App\Form\Type;


use App\Entity\ProblemAttachment;
use App\Entity\Rubric;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProblemAttachmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Filename',
                'required' => true,
            ])
            ->add('visibility', ChoiceType::class, [
                'label' => 'Visibility',
                'required' => false,
                'choices' => [
                    'Public' => 'public',
                    'Hidden' => 'hidden',
                    'Participant' => 'participant',
                    'Other' => 'other',
                    'Private' => 'private',
                ],
                'placeholder' => 'Select visibility',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'required' => false,
                'choices' => [
                    'Pre' => 'pre',
                    'Post' => 'post',
                    'Script' => 'script',
                    'Example' => 'example',
                    'Template' => 'template',
                    'Other (enter below)' => '',
                ],
                'placeholder' => 'Select or enter type',
                'attr' => ['class' => 'attachment-type-select'],
            ])
            ->add('type_custom', TextType::class, [
                'label' => 'Custom Type',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Enter custom type if not listed above',
                ],
            ])
            ->add('url', TextType::class, [
                'label' => 'URL',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('rubric', EntityType::class, [
                'class' => Rubric::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Associated Rubric',
                'placeholder' => 'None',
                'help' => 'Manage rubrics <a href="/jury/problem-rubrics" target="_blank">here</a>.',
                'help_html' => true,
            ])
            ->add('content', FileType::class, [
                'label' => 'Attachment File',
                'required' => true,
                'mapped' => false, // Don't map this to the entity since we handle it manually
            ])
            ->add('add', SubmitType::class, [
                'label' => 'Add Attachment',
                'attr' => [
                    'class' => 'btn-sm btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProblemAttachment::class,
        ]);
    }
}
