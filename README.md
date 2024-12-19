BlackboardBundle
================

This bundles allow download recordings saved on Blackboard Collaborate from Blackboard Learn.

### How to use

1. Add configuration to PuMukIT

```yaml
  learn_host: https://{blackboard_learn_domain}
  learn_key: {blackboard_api_learn_key}
  learn_secret: {blackboard_api_learn_secret}
  collaborate_host: https://{blackboard_collaborate_domain}
  collaborate_key: {blackboard_building_block_collaborate_key}
  collaborate_secret: {blackboard_building_block_collaborate_secret}
```


2. Commands to execute

```bash
php bin/console pumukit:blackboard:sync
```

This command saves on PuMuKIT all info of course and recording to import.


```bash
php bin/console pumukit:blackboard:import:recordings
```

This command import on PuMuKIT recordings that have owners created on PuMuKIT.


3. [OPTIONAL]
