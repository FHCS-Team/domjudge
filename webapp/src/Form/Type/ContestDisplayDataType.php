<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestDisplayData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\Type\KeyValueType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ContestDisplayDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Display Title',
                'required' => false,
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Subtitle',
                'required' => false,
            ])
            ->add('bannerUrl', TextType::class, [
                'label' => 'Banner Image URL',
                'required' => false,
            ])
            ->add('bannerFile', FileType::class, [
                'label' => 'Upload Banner Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/png',
                            'image/jpeg',
                            'image/gif',
                            'image/webp',
                            'image/svg+xml',
                            'image/avif',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (png, jpg, gif, webp, svg, avif).',
                    ]),
                ],
            ])
            // 'allowPhase' removed - phases disabled in this deployment
            ->add('description', TextareaType::class, [
                'label' => 'Description (HTML allowed)',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'class' => 'form-control html-editor',
                ],
            ])
            ->add('mediaFile', FileType::class, [
                'label' => 'Upload Media (image/video)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
                            'video/mp4', 'video/webm', 'video/ogg',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image or video file.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContestDisplayData::class,
        ]);
    }
}
