# StableCompanion (SC)

## IMPORTANT: This project is still "work in progress"! Do not use it for now, as there will be unannounced changes in everything! Much of the stuff in this readme-file is for future versions!

## About
StableCompanion is a side-software meant to accompany an already installed instance of A1111s-WebUI.
It was build out of the problem that there are maaaaany extensions out there, but none of them did quite what I wanted in some cases.
For example: There is no good (as in "easy") extension or script to test one prompt against all installed models.  
Plus - there is no good extension there for model-, LoRA- or Embedding-management. So my harddrive becomes 
cluttered with all those cool models where I have no clue on how to use them properly. Yes - there are docs, but who can memorize those?   

## Important premise
I've build StableCompanion for myself as part of my "recreational programming". It has no intention to specifically fit your usecases, but mine. Hopefully you find it usefull aswell. :)  
And before someone asks "Why a side-software written in PHP? Couldn't you just wrote a normal A1111-Extension like any normal person out there?" - Well..
I could... But that would imply to learn Python and stuff, which I don't have the time right now. But I know PHP, I know Laravel and how to do stuff with it. 
So take it, or leave it. :)  
Plus - technically you can use StableCompanion to also manage your models for ComfyUI, InvokeAI and whatnot. The toolbox will only depend on A1111.

## Working Features
### Checkpoints
  - Management of existing checkpoints (CRUD)
  - Import checkpoints from CivitAI

## Planned Features
- Import Checkpoints, LoRAs and Embeddings from CivitAI
- Manage your Checkpoints, LoRAs and Embeddings
- Toolbox for testing and playing around
- Build prompts and store them

## Setup
### Prerequisites
- OS: Does not matter
- installed Docker-Engine and docker-compose
- Installed and running instance of A1111-WebUI with the following parameters:
  - `--api` in launch-commands
  - Following extensions installed:
    - Adetailer (https://github.com/Bing-su/adetailer)
    - Regional Prompter (https://github.com/hako-mikan/sd-webui-regional-prompter)
    - ControlNet (https://github.com/Mikubill/sd-webui-controlnet)
### Setup
  1. clone this repo or download a release 
  2. cd into the docker-directory and copy the docker-compose.yml.original to docker-compose.yml
  3. modify the newly created docker-compose.yml to your need (should be self-explaining inside the file)
  4. fireup A1111-WebUI (if not already up)
  5. run `docker-compose up -d` inside the docker-directory and go to http://localhost:7861
### Usage
  There are some "rules" you should follow when using StableCompanion. Here they are:
- Do NOT rename files within the checkpoints-, loras- or embedding-directory (that also includes changing file-extensions)! StableCompanion will detect them as "new" files which will lead to duplicates in the database!
- StableCompanion does create the folders "sd" and "xl" inside your checkpoint-, lora- and embedding-volumes. This is done to provide a file-separation for regular models and Xl-stuff. 
- When you import a checkpoint, lora, or embedding from CivitAI - StableCompanion will put the CivitAI-ID and -versionID in front of the filename. This is so that a new import or update does not accidentally overwrites an existing file if the model-maintainer keeps using repetitive filenames.
- Within each folder (checkpoints, loras, embeddings) you can create a directory with the name "no_scan" - StableCompanion scans your files recursively, but EXPLICITLY ignore that directory. In there you can put all your files that are still in training or should not be used by StableCompanion at all.
- All requests against the CivitAI-API are beeing cached for one hour or until the container is restarted. I just try to play nice here - so please follow suit. If you wanna look for updates - there are buttons for that you can hit once every hour.
- If you read `INFO gave up: startup entered FATAL state, too many start retries too quickly`in the logs - that's nothing to worry about. The startup-script bites itself a bit with supervisor. As long as php-fpm, nginx and aria are running you are fine. :)
- RTFM and READ THE TEXTS ON SCREEN! I've tried to make you aware of what is happening in every step, so please read what is on screen and make conscious clicks. :)
- Sometimes, when you download multiple files at once (max. 5) it's possible that they show up as 100%-done in the downloads tab. This is due to a bug in Aria2. Just wait a bit (10 seconds) if you are unsure.

## PAQ
Since there are no "Frequently asked questions" yet I'm doing a "Possible asked questions." :)
- All the talk about "Linking to CivitAI" inside the software - do you send data there? -> Nope. Everything runs locally on your machine. There are downloads, but no uploads!
- Is there a way to import a specific image from CivitAI? -> Unfortunately the CivitAI-API is borked when it comes to this and so far no one was able to tell me how that should work. I get empty results when I try `https://civitai.com/api/v1/images?postId=123`
- Can I import all the images of a checkpoint/lora/embedding? -> Nope. StableCompanion relies on the CivitAI-API and that gives me only 10 images max. Plus - see above.
- Will you optimize this for mobile-devices? -> Well - I will not. If you really need that, feel encouraged to make a PR. :)
- Whenever I import something from CivitAI, the number of downloads in the left menu does not go up. Why? -> That is one of the weak points of Filament. AFAIK there is no solution to that right now.
- Will you support downloads from Huggingface or somewhere else aswell? -> Not for now. If you have something from Huggingface or somewhere else, I would recommend to just download the file and put it wherever it belongs on your filesystem.

## Legal and stuff
I'm releasing StableCompanion "as is" and take no responsibility of what you are doing with it. Please follow your local laws and respect the terms of the models and files you are downloading and managing.  
Under no circumstances are you allowed to redistribute StableCompanion in any context!  
If you use SC or parts of it for your own project/software/what-not: Just drop a link to this repo and my Github-Profile in there for people to find.