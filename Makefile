REPO_PREFIX=scottaubrey/elifesciences-journal
IMAGE_TAG=develop

build-app:
	docker-compose -f docker-compose.combined.yml build

install-dependencies:
	@# These install dependencies into the working tree for the bind mount to the docker container to work
	docker-compose --profile=dependencies -f docker-compose.combined.yml run composer
	docker-compose --profile=dependencies -f docker-compose.combined.yml run assets

start: build-app install-dependencies
	docker-compose -f docker-compose.combined.yml up || true

stop:
	docker-compose -f docker-compose.combined.yml down --remove-orphans --volumes || true

test: test-composer test-phpcs test-phpunit test-behat test-smoke

test-composer:
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests .ci/composer_platform
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests .ci/composer_validate
test-phpcs:
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests .ci/phpcs
test-phpunit:
	docker-compose --profile=tests -f docker-compose.combined.yml up -d || true
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests .ci/phpunit
	docker-compose --profile=tests -f docker-compose.combined.yml down
test-behat:
	docker-compose --profile=tests -f docker-compose.combined.yml up -d || true
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests .ci/behat
	docker-compose --profile=tests -f docker-compose.combined.yml down

test-smoke:
	docker-compose --profile=tests -f docker-compose.combined.yml up -d || true
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests smoke_tests.sh web 80
	docker-compose --profile=tests -f docker-compose.combined.yml run --rm tests status_test.sh web 80
	docker-compose --profile=tests -f docker-compose.combined.yml down

clean-test:
	docker-compose -f docker-compose.combined.yml down
	docker image rm $(REPO_PREFIX):tests

build:
	docker buildx build --load -f Dockerfile.combined --target app . -t $(REPO_PREFIX):$(IMAGE_TAG) -t $(REPO_PREFIX):latest

buildx-and-push:
	docker buildx build --push --platform linux/amd64,linux/arm64  -f Dockerfile.combined --target app . -t $(REPO_PREFIX):$(IMAGE_TAG) -t $(REPO_PREFIX):latest

build-critical-css:
	@# Building critical css requires a full stack to be running
	@# the output will be dumped into the working tree, then copied into the image by a Dockerfile target
	docker-compose --profile=dependencies -f docker-compose.combined.yml run critical-css
	docker-compose -f docker-compose.combined.yml down
	docker-compose -f docker-compose.combined.yml build app --no-cache # rebuild the main image now we have critical-css output

clean:
	docker-compose -f docker-compose.combined.yml down --remove-orphans --volumes --rmi all || true
	rm -Rf build/assets || true
	rm -Rf build/critical-css/* || true
	rm -f build/rev-manifest.json || true
	rm -Rf vendor || true
	rm -Rf node_modules || true
	rm -Rf web/assets || true
	rm -f web/favicon.ico || true

.PHONY: build build-app install-dependencies build-critical-css stop clean test buildx-and-push
