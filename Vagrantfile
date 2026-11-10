require 'yaml'
require 'fileutils'

required_plugins_installed = nil
required_plugins = %w( vagrant-hostmanager vagrant-vbguest )
required_plugins.each do |plugin|
  unless Vagrant.has_plugin? plugin
    system "vagrant plugin install #{plugin}"
    required_plugins_installed = true
  end
end

if required_plugins_installed
  system 'vagrant' + ARGV.to_s.gsub(/\[\"|\", \"|\"\]/, ' ')
  exit
end

domains = {
  app: 'yii2basic.test'
}

vagrantfile_dir_path = File.dirname(__FILE__)

config = {
  local: vagrantfile_dir_path + '/vagrant/config/vagrant-local.yml',
  example: vagrantfile_dir_path + '/vagrant/config/vagrant-local.example.yml'
}

FileUtils.cp config[:example], config[:local] unless File.exist?(config[:local])
options = YAML.load_file config[:local]

if options['github_token'].nil? || options['github_token'].to_s.length != 40
  puts "You must place REAL GitHub token into configuration:\n/yii2-app-basic/vagrant/config/vagrant-local.yml"
  exit
end

Vagrant.configure(2) do |config|
  config.vm.box = 'bento/ubuntu-18.04'

  config.vm.box_check_update = options['box_check_update']

  config.vm.provider 'virtualbox' do |vb|
    vb.cpus = options['cpus']
    vb.memory = options['memory']
    vb.name = options['machine_name']
  end

  config.vm.define options['machine_name']

  config.vm.hostname = options['machine_name']

  config.vm.network 'private_network', ip: options['ip']

  config.vm.synced_folder './', '/app', owner: 'vagrant', group: 'vagrant'

  config.vm.synced_folder '.', '/vagrant', disabled: true

  config.vm.provision :hostmanager
  config.hostmanager.enabled            = true
  config.hostmanager.manage_host        = true
  config.hostmanager.ignore_private_ip  = false
  config.hostmanager.include_offline    = true
  config.hostmanager.aliases            = domains.values

  config.vm.provision 'shell', path: './vagrant/provision/once-as-root.sh', args: [options['timezone'], options['ip']]
  config.vm.provision 'shell', path: './vagrant/provision/once-as-vagrant.sh', args: [options['github_token']], privileged: false
  config.vm.provision 'shell', path: './vagrant/provision/always-as-root.sh', run: 'always'

  config.vm.post_up_message = "App URL: http://#{domains[:app]}"
end
