"use strict";

// Currently, the config is defined inside the html via js variable "modelViewerOptions".

document.addEventListener('DOMContentLoaded', function(event) {

    // The config is defined inside the html.
    if (typeof modelViewerOptions === 'undefined') {
        return;
    }

    // console.log(modelViewerOptions);

    function createScene(options) {

        // ===== //
        // Setup //
        // ===== //

        const modelSource = options.source;
        if (!modelSource) {
            console.log('No source.');
            return;
        }

        const viewerElement = document.getElementById(options.id);
        if (!viewerElement) {
            console.log('No container.');
            return;
        }

        if (!document.querySelector('#' + options.id + ' canvas')) {
            viewerElement.appendChild(document.createElement('canvas'));
        }
        const canvas = document.querySelector('#' + options.id + ' canvas');

        const background = options.config && options.config.background && options.config.background.length
            ? options.config.background : 'white';

        const modelScale = options.config && options.config.modelScale
            ? options.config.modelScale
            : 1;

        const pointLightPosition = options.config && options.config.pointLightPosition
            ? options.config.pointLightPosition
            : {x: 0, y: 50, z: 15};

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(background);

        // ====== //
        // Loader //
        // ====== //

        const manager = new THREE.LoadingManager();
        const loader = new THREE.GLTFLoader(manager);

        const progressLoader = document.createElement('div');
        {
            progressLoader.className = 'loader';
            let progressMessage = document.createElement('p');
            progressMessage.appendChild(document.createTextNode('Loading'));
            progressLoader.appendChild(progressMessage);

            let progress = document.createElement('div');
            progress.id = 'progress';
            progress.className = 'progress';

            var progressBar = document.createElement('div');
            progressBar.id = 'progress-bar';
            progressBar.className = 'progress-bar';

            progress.appendChild(progressBar);
            progressLoader.appendChild(progress);
            viewerElement.appendChild(progressLoader);

            manager.onProgress = function (item, loaded, total) {
                progressBar.style.width = (loaded / total * 100) + '%';
            }
        }

        // ======== //
        // Renderer //
        // ======== //

        const renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            // TODO Disable antialias on low mobile devices.
            antialias: true,
            alpha: true,
        });
        renderer.setClearColor(0x363636, 1);
        renderer.shadowMap.enabled = true;

        //====== //
        // Sizes //
        // ===== //

        let sizes = {};

        // ======= //
        // Cameras //
        // ======= //

        const camera = new THREE.PerspectiveCamera(100, 0, 0.1, 1000);
        camera.position.set(50, 50, 80);
        scene.add(camera);

        // ====== //
        // Meshes //
        // ====== //

        let anisotropy;
        anisotropy = renderer.capabilities.getMaxAnisotropy();

        let model;
        loader.load(modelSource, gltf => {
            model = gltf.scene;
            model.scale.set(modelScale, modelScale, modelScale);

            model.traverse((child) => {
                if (child instanceof THREE.Mesh) {
                    child.material = new THREE.MeshStandardMaterial({
                        map: child.material.map,
                    })
                    if (child.material.map) {
                        // child.material.map.magFilter = THREE.NearestFilter;
                        // child.material.map.minFilter = THREE.LinearMipMapLinearFilter;
                        child.material.map.anisotropy = anisotropy / 2;
                        child.material.side = THREE.DoubleSide;
                    }
                    child.castShadow = true;
                    child.receiveShadow = true;
                }
            });

            setTimeout(() => {
                progressLoader.style.opacity = 0;
                setTimeout(() => {
                     progressLoader.style.display = 'none';
                     viewerElement.removeChild(progressLoader);
                }, 200);
            }, 1000);

            scene.add(model);
        });

        // ====== //
        // Lights //
        // ====== //

        const ambientLight = new THREE.AmbientLight(0xffffff, 0.75);
        scene.add(ambientLight);

        const pointLight = new THREE.PointLight(0xffffff, 0.5, 100, 1);
        pointLight.position.set(pointLightPosition.x, pointLightPosition.y, pointLightPosition.z);
        pointLight.castShadow = false;
        pointLight.shadow.mapSize.height = 8;
        pointLight.shadow.mapSize.width = 8;

        const pointLight2 = new THREE.PointLight(0xffffff, 0.5);
        pointLight2.position.z = 30;

        const lightGroup = new THREE.Group()
        for (var j = 1; j <= 7; j++) {
            for (var i = 1; i <= 5; i++) {
                var pointLightTemp = pointLight2.clone();
                pointLightTemp.castShadow = false;
                pointLightTemp.position.z = j * 8 - 30;
                pointLightTemp.intensity = 0.009;
                pointLightTemp.position.y += 2;
                pointLightTemp.position.x = i * 6 - 20;
                pointLightTemp.shadow.mapSize.height = 512;
                pointLightTemp.shadow.mapSize.width = 512;
                lightGroup.add(pointLightTemp);
            }
        }
        lightGroup.rotation.y = -Math.PI / 25;
        scene.add(lightGroup);

        const pointLight3 = pointLight2.clone();
        pointLight3.position.z = -30;
        // scene.add(pointLight2);
        // scene.add(pointLight3);

        const helper = new THREE.PointLightHelper(pointLight);
        // scene.add(helper);
        // scene.add(pointLight);

        // ======== //
        // Controls //
        // ======== //

        let control;
        let target = new THREE.Vector3(0, 0, 0);
        const controlSpeed = 0.8;
        const orbitSpeed = 0.4;
        const scrollSpeed = 0.8;

        function scrollListener(event) {
            camera.getWorldDirection(direction);
            if (event.deltaY < 0) {
                const diffence = Math.abs(camera.position.x - control.target.x)
                    + Math.abs(camera.position.y - control.target.y)
                    + Math.abs(camera.position.z - control.target.z);
                if (diffence < 10) {
                    control.target.addScaledVector(direction, scrollSpeed);
                }
            }
        }

        let euler = new THREE.Euler(0, 0, 0, 'YXZ');
        let rotationSpeed = (Math.PI / 180) / 5;

        function onMouseDown(e) {
            canvas.addEventListener('mousemove', onMouseMove);
        }

        function onMouseMove(e) {
            if (lockCamera == false) {
                const movementX = e.movementX || e.mozMovementX || e.webkitMovementX || 0;
                const movementY = e.movementY || e.mozMovementY || e.webkitMovementY || 0;

                euler.y -= movementX * rotationSpeed;
                euler.x -= movementY * rotationSpeed;
                euler.x = Math.min(Math.max(euler.x, -1.0472), 1.0472);

                camera.quaternion.setFromEuler(euler);
            }
        }

        function enableOrbitControls() {
            canvas.removeEventListener('mousedown', onMouseDown);
            canvas.removeEventListener('mouseup', onMouseMove);

            if (control != undefined) {
                control.dispose();
            }

            control = new THREE.OrbitControls(camera, canvas);
            control.zoomSpeed = 0.4;
            control.zoomSpeed = controlSpeed;
            control.enableDamping = true;
            control.enablePan = true;
            control.target = target;

            canvas.addEventListener('wheel', scrollListener);
        }

        enableOrbitControls();

        control.enabled = false;

        var lockCamera = true;

        // ==== //
        // Loop //
        // ==== //

        const direction = new THREE.Vector3;
        const clock = new THREE.Clock();
        const tick = () => {
            // Render.
            renderer.render(scene, camera);

            // Call tick again on the next frame.
            window.requestAnimationFrame(tick);

            // Update control.
            control.update();
            if (lockCamera) {
                camera.lookAt(0, 0, 0);
            }
        }
        tick();

        // ============ //
        // Resize Event //
        // ============ //

        function updateSizes() {
            // Update sizes.
            sizes = {
                height: window.innerHeight,
                width: window.innerWidth,
            }

            // Update camera.
            camera.aspect = sizes.width / sizes.height;
            camera.updateProjectionMatrix();

            // Update renderer.
            renderer.setSize(sizes.width, sizes.height);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        }

        updateSizes();

        window.addEventListener('resize', () => {
            updateSizes();
        })
    }

    modelViewerOptions.forEach(function (options, index) {
        createScene(options);
    });

});
