<?php

namespace Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Guzzle\Http\Client;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Http\Message\Header;

class RunCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Inicia o monitoramento')
            ->setHelp(<<<EOT
Sistema para monitoramento do site de agendamento para seguro desemprego

EOT
            )
            ->addOption(
                'estado',
                'e',
                InputOption::VALUE_REQUIRED,
                'sigla do estado que deseja verificar o agendamento'
            )
            ->addOption(
                'municipio',
                'm',
                InputOption::VALUE_REQUIRED,
                'Código do município encontrado nas options do <select> do site http://saaweb.mte.gov.br/'
            )
            ->addOption(
                'unidade',
                'u',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Lista com código das  unidades a verificar'
            )
            ->addOption(
                'cpf',
                'c',
                InputOption::VALUE_REQUIRED,
                'Número do CPF, nnn.nnn.nnn-nn'
            )
            ->addOption(
                'nascimento',
                'd',
                InputOption::VALUE_REQUIRED,
                'Data de nascimento DD/MM/YYYY'
            )
            ->addOption(
                'telefone',
                't',
                InputOption::VALUE_REQUIRED,
                'Telefone para contato (DD)NNNNNNNN'
            )
            ->addOption(
                'intervalo',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Intervalo em segundos entre as requisições',
                30
            )
            ->addOption(
                'tipo',
                null,
                InputOption::VALUE_OPTIONAL,
                'Tipo de atendimento desejado [1 = entrada seguro desemprego],',
                1
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client();
        if($output->getVerbosity() > 1) {
            $client->setDefaultOption('debug', true);
        }
        $res = $client->get(
            'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam',
            ['User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0']
        )->send();
        $cookie = $res->getHeader('set-cookie')->parseParams()[0]['JSESSIONID'];

        $dom = new \DOMDocument();
        @$dom->loadHTML($res->getBody(true));
        $domxpath = new \DOMXPath($dom);
        $options = $domxpath->query('//select[@id="frmInicioExterno:j_id44:slUf"]/option');
        foreach($options as $option) {
            $estados[$option->getAttribute('value')] = $option->nodeValue;
        }
        if(!array_key_exists($input->getOption('estado'), $estados)) {
            $output->writeln('<error>Estado informado inexistente</error>');
            return;
        }
        $viewState = $domxpath->query('//input[@name="javax.faces.ViewState"]')->item(0)->getAttribute('value');

        $res = $client->post(
            'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam;jsessionid='.$cookie,
            [
                'Cookie'=> 'JSESSIONID='.$cookie,
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0'
            ],
            [
                'AJAXREQUEST'=>'_viewRoot',
                'frmInicioExterno' => 'frmInicioExterno',
                'frmInicioExterno:decorateMunicipio:slMunicipioEndereco' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
                'frmInicioExterno:decorateUnidade:slUnidade' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
                'frmInicioExterno:dcTipoAtendimento:j_id70' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
                'javax.faces.ViewState' => $viewState,
                'frmInicioExterno:j_id44:slUf' => $input->getOption('estado'),
                'ajaxSingle' => 'frmInicioExterno:j_id44:slUf',
                'frmInicioExterno:j_id44:j_id50' => 'frmInicioExterno:j_id44:j_id50'
            ]
        )->send();
        @$dom->loadHTML($res->getBody(true));
        $domxpath = new \DOMXPath($dom);
        $options = $domxpath->query('//option');
        foreach($options as $option) {
            $municipio[$option->getAttribute('value')] = $option->nodeValue;
        }
        if(!array_key_exists($input->getOption('municipio'), $municipio)) {
            $output->writeln('<error>Municipio informado inexistente no '.$estados[$input->getOption('estado')].'</error>');
            return;
        }
        $output->writeln('Município selecionado: '.$municipio[$input->getOption('municipio')]);
        $viewState = $domxpath->query('//input[@name="javax.faces.ViewState"]')->item(0)->getAttribute('value');
        $res = $client->post(
            'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam;jsessionid='.$cookie,
            [
                'Cookie'=> 'JSESSIONID='.$cookie,
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0'
            ],
            [
                'AJAXREQUEST' => '_viewRoot',
                'frmInicioExterno' => 'frmInicioExterno',
                'frmInicioExterno:j_id44:slUf' => $input->getOption('estado'),
                'frmInicioExterno:decorateUnidade:slUnidade' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
                'frmInicioExterno:dcTipoAtendimento:j_id70' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
                'javax.faces.ViewState' => $viewState,
                'frmInicioExterno:decorateMunicipio:slMunicipioEndereco' => $input->getOption('municipio'),
                'frmInicioExterno:decorateMunicipio:j_id57' => 'frmInicioExterno:decorateMunicipio:j_id57',
                'ajaxSingle' => 'frmInicioExterno:decorateMunicipio:slMunicipioEndereco'
            ]
        )->send();
        @$dom->loadHTML($res->getBody(true));
        $domxpath = new \DOMXPath($dom);
        $options = $domxpath->query('//option');
        foreach($options as $option) {
            $unidades[$option->getAttribute('value')] = $option->nodeValue;
        }
        $output->writeln('Unidades selecionadas:');
        foreach($input->getOption('unidade') as $input_unidade) {
            if(!array_key_exists($input_unidade, $unidades)) {
                $output->writeln('<error>Unidade '.$input_unidade.' não pertence ao município</error>');
                return;
            }
            $output->writeln('    '.str_pad($input_unidade, 2,' ').': '.$unidades[$input_unidade]);
            $unidades_selecionadas[] = $input_unidade;
            $ok = true;
        }
        if(!$ok) {
            $output->writeln('<error>Unidades inválidas ou não pertencentes ao município</error>');
            return;
        }
        $viewState = $domxpath->query('//input[@name="javax.faces.ViewState"]')->item(0)->getAttribute('value');

        $this->iteration = 0;
        do {
            foreach($unidades_selecionadas as $unidade) {
                $res = $client->post(
                    'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam;jsessionid='.$cookie,
                    [
                        'Cookie'=> 'JSESSIONID='.$cookie,
                        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0'
                    ],
                    [
                        'AJAXREQUEST' => '_viewRoot',
                        'frmInicioExterno' => 'frmInicioExterno',
                        'frmInicioExterno:j_id44:slUf' => $input->getOption('estado'),
                        'frmInicioExterno:decorateMunicipio:slMunicipioEndereco' => $input->getOption('municipio'),
                        'frmInicioExterno:dcTipoAtendimento:j_id70' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
                        'javax.faces.ViewState' => $viewState,
                        'frmInicioExterno:decorateUnidade:slUnidade' => $unidade,
                        'ajaxSingle' => 'frmInicioExterno:decorateUnidade:slUnidade',
                        'frmInicioExterno:decorateUnidade:j_id64' => 'frmInicioExterno:decorateUnidade:j_id64'
                    ]
                )->send();
                @$dom->loadHTML($res->getBody(true));
                $domxpath = new \DOMXPath($dom);
                $options = $domxpath->query('//option');
                $tipo = null;
                $viewState = $domxpath->query('//input[@name="javax.faces.ViewState"]')->item(0)->getAttribute('value');
                foreach($options as $option) {
                    switch($input->getOption('tipo')) {
                        case 1:
                            if(strpos(strtolower($option->nodeValue), 'entrada no seguro desemprego') !== false) {
                                $tipo = $option->getAttribute('value');
                                $output->writeln('Tipo de requisição: '.$option->nodeValue);

                                $res = $client->post(
                                    'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam;jsessionid='.$cookie,
                                    [
                                        'Cookie'=> 'JSESSIONID='.$cookie,
                                        'Referer' => 'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam',
                                        'Connection' => 'keep-alive',
                                        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0',
                                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                                    ],
                                    [
                                        'frmInicioExterno' => 'frmInicioExterno',
                                        'frmInicioExterno:j_id44:slUf' => $input->getOption('estado'),
                                        'frmInicioExterno:decorateMunicipio:slMunicipioEndereco' => $input->getOption('municipio'),
                                        'frmInicioExterno:decorateUnidade:slUnidade' => $unidade,
                                        'frmInicioExterno:dcTipoAtendimento:j_id70' => $tipo,
                                        'frmInicioExterno:botoesField:j_id76' => 'Prosseguir',
                                        'javax.faces.ViewState' => $viewState
                                    ]
                                )->send();
                                @$dom->loadHTML($res->getBody(true));
                                $domxpath = new \DOMXPath($dom);
                                $viewState = $domxpath->query('//input[@name="javax.faces.ViewState"]')->item(0)->getAttribute('value');
                                $res = $client->post(
                                    'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/identificacaoUsuario.seam',
                                    [
                                        'Cookie'=> 'JSESSIONID='.$cookie,
                                        'Referer' => 'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/main.seam;jsessionid='.$cookie,
                                        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0'
                                    ],
                                    [
                                        'AJAXREQUEST' => '_viewRoot',
                                        'frmIdentUser' => 'frmIdentUser',
                                        'frmIdentUser:nrCpfDecorate:nrCpf' => $input->getOption('cpf'),
                                        'frmIdentUser:dtNascimentoDecorate:dtNascimento' => $input->getOption('nascimento'),
                                        'frmIdentUser:txtTelefone1Decorate:txtTelefone1' => $input->getOption('telefone'),
                                        'frmIdentUser:j_id65:mdReagendarOpenedState' => '',
                                        'frmIdentUser_link_hidden_' => 'frmIdentUser:botoesField:j_id83',
                                        'javax.faces.ViewState' => $viewState,
                                        'frmIdentUser:botoesField:j_id84' => 'frmIdentUser:botoesField:j_id84'
                                    ]
                                )->send();
                                $redirect = $res->getHeader('Location')->toArray()[0];
                                $res = $client->get('http://saaweb.mte.gov.br'.$redirect,
                                    [
                                        'Cookie'=> 'JSESSIONID='.$cookie
                                    ]
                                )->send();
                                @$dom->loadHTML($res->getBody(true));
                                $domxpath = new \DOMXPath($dom);
                                $viewState = $domxpath->query('//input[@name="javax.faces.ViewState"]')->item(0)->getAttribute('value');
                                $calendario = function($client, $viewState, $cookie, $self, $increment = 0, $_this){
                                    $res = $client->post(
                                        'http://saaweb.mte.gov.br/saa-internet/pages/agendamento/selecioneHorario.seam',
                                        [
                                            'Cookie'=> 'JSESSIONID='.$cookie,
                                            'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0'
                                        ],
                                        [
                                            'AJAXREQUEST' => '_viewRoot',
                                            'frmIdentUser' => 'frmIdentUser',
                                            'frmIdentUser:calendarInputDate' => '',
                                            'frmIdentUser:calendarInputCurrentDate' => date('m/Y', mktime(0, 0, 0, date("m")+$increment , date("d"), date("Y"))),
                                            'javax.faces.ViewState' => $viewState,
                                            'frmIdentUser:j_id55' => 'frmIdentUser:j_id55',
                                            'AJAX:EVENTS_COUNT' => 1
                                        ]
                                    )->send();
                                    $dom = new \DOMDocument();
                                    @$dom->loadHTML($res->getBody(true));
                                    $domxpath = new \DOMXPath($dom);
                                    $calendar = $domxpath->query('//div[@id="frmIdentUser:calendarScript"]/script')->item(0)->nodeValue;
                                    preg_match('/load\((.*)\);/', $calendar, $calendar);
                                    $calendar = $calendar[1];
                                    $json = str_replace("'", '"', $calendar);
                                    $json = preg_replace('/(new Date\([0-9|,]{0,}\))/', '"$1"', $json);
                                    $calendar = $json = str_replace('\x2D', '-', $json);
                                    $json = json_decode($json, true);
                                    end($json['days']);
                                    if(!$increment && !$json['days'][key($json['days'])]['enabled']) {
                                        $self($client, $viewState, $cookie, $self, 1, $_this);
                                    } else {
                                        if(!isset($this->unidades[$unidade])) {
                                            $this->unidades[$unidade] = md5($calendar);
                                        } else {
                                            if($this->unidades[$unidade] != md5($calendar)) {

                                            }
                                        }
                                        return;
                                    }
                                };
                                $calendario($client, $viewState, $cookie, $calendario, 0, $this);
                                break;
                            }
                    }
                }
                if(!$tipo) {
                    $output->writeln('<error>Tipo '.$input->getOption('tipo').' não pertence a unidade '.$unidades[$unidade].'</error>');
                }
                break;
            }
            $this->iteration++;
        } while(true);
    }
}
