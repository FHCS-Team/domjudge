<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Language;
use App\Entity\Problem;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class HackathonSubmitType extends AbstractType
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EntityManagerInterface $em
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowMultipleFiles = $this->config->get('sourcefiles_limit') > 1;
        $user               = $this->dj->getUser();
        $contest            = $this->dj->getCurrentContest($user->getTeam()->getTeamid());

        // Standard code submission
        $builder->add('code', FileType::class, [
            'label' => 'Source Code' . ($allowMultipleFiles ? ' Files' : ''),
            'multiple' => $allowMultipleFiles,
            'help' => 'Upload your source code files for automated testing',
        ]);

        // Problem selection
        $problemConfig = [
            'class' => Problem::class,
            'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('p')
                ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
                ->select('p', 'cp')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter('contest', $contest)
                ->addOrderBy('cp.shortname'),
            'choice_label' => fn(Problem $problem) => sprintf(
                '%s: %s',
                $problem->getContestProblems()->first()->getShortname(),
                $problem->getName()
            ),
            'placeholder' => 'Select a problem',
        ];

        $builder->add('problem', EntityType::class, $problemConfig);

        // Language selection - Commented out for hackathons since they don't need language-specific judging
        /*
        $builder->add('language', EntityType::class, [
            'class' => Language::class,
            'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('lang')
                ->andWhere('lang.allowSubmit = true')
                ->addOrderBy('lang.name'),
            'choice_label' => fn(Language $language) => $language->getName(),
            'placeholder' => 'Select a language',
        ]);
        */

        // Submission Method Selection
        $builder->add('submission_method', ChoiceType::class, [
            'label' => 'Submission Method',
            'choices' => [
                'File Upload Only' => 'files',
                'GitHub Repository' => 'github',
                'Live Demo URL' => 'demo',
                'Files + GitHub' => 'files_github',
                'Files + Demo' => 'files_demo',
                'GitHub + Demo' => 'github_demo',
                'Complete (All Methods)' => 'complete',
            ],
            'expanded' => true,
            'mapped' => false,
            'data' => 'files', // Default selection
            'help' => 'Choose how you want to submit your solution',
        ]);

        // GitHub Repository URL
        $builder->add('github_url', TextType::class, [
            'label' => 'GitHub Repository URL',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'placeholder' => 'https://github.com/username/repository',
                'class' => 'submission-field github-field',
            ],
            'help' => 'Link to your GitHub repository (must be public or accessible)',
        ]);

        // Live Demo URL
        $builder->add('demo_url', TextType::class, [
            'label' => 'Live Demo URL',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'placeholder' => 'https://your-demo.herokuapp.com',
                'class' => 'submission-field demo-field',
            ],
            'help' => 'Link to your working demo/application',
        ]);

        // Video Demo URL (optional)
        $builder->add('video_url', TextType::class, [
            'label' => 'Video Demo URL (Optional)',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'placeholder' => 'https://www.youtube.com/watch?v=...',
                'class' => 'submission-field video-field',
            ],
            'help' => 'Link to video demonstration (YouTube, Vimeo, etc.)',
        ]);

        // Deliverable Type Selection
        $builder->add('deliverable_types', ChoiceType::class, [
            'label' => 'Deliverable Types',
            'choices' => [
                'Web Application' => 'web_app',
                'CLI Application' => 'cli_app',
                'Mobile App' => 'mobile_app',
                'API/Service' => 'api_service',
                'Documentation' => 'documentation',
                'Presentation' => 'presentation',
                'Other' => 'other',
            ],
            'multiple' => true,
            'expanded' => true,
            'help' => 'Select all types that apply to your submission',
            'required' => false,
        ]);

        // Deliverable Files Upload
        $builder->add('deliverable_files', FileType::class, [
            'label' => 'Deliverable Files',
            'multiple' => true,
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new File([
                    'maxSize' => '100M',
                    'mimeTypes' => [
                        'application/zip',
                        'application/x-tar',
                        'application/gzip',
                        'application/x-rar-compressed',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    ],
                    'mimeTypesMessage' => 'Please upload valid archive files, PDFs, or Office documents.',
                ]),
            ],
            'help' => 'Upload deliverable files (ZIP, TAR, PDF, etc.). Max 100MB per file.',
        ]);

        // Solution Description
        $builder->add('description', TextareaType::class, [
            'label' => 'Solution Description',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'rows' => 4,
                'placeholder' => 'Briefly describe your solution, approach, and any special features...',
            ],
            'help' => 'Optional: Describe your solution approach, technologies used, and key features.',
        ]);

        // Deployment Instructions
        $builder->add('deployment_instructions', TextareaType::class, [
            'label' => 'Deployment/Usage Instructions',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'rows' => 3,
                'placeholder' => 'How to run/deploy your solution...',
            ],
            'help' => 'Optional: Provide instructions for running or deploying your solution.',
        ]);

        // Dynamic form modification based on problem selection
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            if (isset($data['problem'])) {
                $this->setupProblemSpecificFields($event->getForm(), $data['problem']);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (isset($data['problem'])) {
                $problem = $this->em->getRepository(Problem::class)->find($data['problem']);
                if ($problem) {
                    $this->setupProblemSpecificFields($event->getForm(), $problem);
                }
            }
        });
    }

    private function setupProblemSpecificFields(Form $form, Problem $problem): void
    {
        // You can add problem-specific fields here based on the selected problem
        // For example, different deliverable requirements per problem
    }

    public function getBlockPrefix(): string
    {
        return 'hackathon_submit';
    }
}