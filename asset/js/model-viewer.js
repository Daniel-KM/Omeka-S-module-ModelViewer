"use strict";

import * as THREE from '../vendor/threejs/three.module.min.js';
import { OrbitControls } from '../vendor/threejs/jsm/controls/OrbitControls.js';
import { GLTFLoader } from '../vendor/threejs/jsm/loaders/GLTFLoader.js';
import { ColladaLoader } from '../vendor/threejs/jsm/loaders/ColladaLoader.js';
import { DDSLoader } from '../vendor/threejs/jsm/loaders/DDSLoader.js';
import { FBXLoader } from '../vendor/threejs/jsm/loaders/FBXLoader.js';
import { MTLLoader } from '../vendor/threejs/jsm/loaders/MTLLoader.js';
import { OBJLoader } from '../vendor/threejs/jsm/loaders/OBJLoader.js';
import { RoomEnvironment } from '../vendor/threejs/jsm/environments/RoomEnvironment.js';

document.addEventListener('DOMContentLoaded', function () {

    if (typeof modelViewerOptions === 'undefined') {
        return;
    }

    /**
     * Fit camera so the object fills the viewport.
     */
    function fitCameraToObject(camera, object, control) {
        const box = new THREE.Box3().setFromObject(object);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        if (maxDim === 0) return;

        const fov = camera.fov * (Math.PI / 180);
        const dist = (maxDim / 2) / Math.tan(fov / 2) * 1.5;

        camera.position.set(
            center.x + dist * 0.5,
            center.y + dist * 0.4,
            center.z + dist * 0.8
        );
        camera.near = dist / 100;
        camera.far = dist * 100;
        camera.updateProjectionMatrix();
        camera.lookAt(center);

        if (control) {
            control.target.copy(center);
            control.update();
        }
    }

    /**
     * Create a Three.js scene for one model viewer.
     */
    function createScene(options) {

        const modelSource = options.source;
        if (!modelSource) return;

        const viewerElement = document.getElementById(options.id);
        if (!viewerElement) return;
        if (viewerElement.dataset.sceneReady) return;
        viewerElement.dataset.sceneReady = '1';

        const config = options.config || {};

        if (!viewerElement.querySelector('canvas')) {
            viewerElement.appendChild(document.createElement('canvas'));
        }
        const canvas = viewerElement.querySelector('canvas');

        const background = config.background || 'white';
        const modelScale = config.scale || 1;
        const matcapTextureFile = config.matcap_texture || null;

        // Scene.
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(background);

        // Loading manager.
        const manager = new THREE.LoadingManager();
        const progressLoader = createProgressUI(viewerElement, manager);

        // Renderer.
        const renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            antialias: true,
            alpha: true,
        });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setSize(canvas.clientWidth, canvas.clientHeight);
        renderer.setClearColor(scene.background, 1);
        renderer.shadowMap.enabled = true;
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.NoToneMapping;

        // Camera (default, will be repositioned by auto-fit).
        const cameraConfig = (config.cameras && config.cameras.length)
            ? config.cameras[0]
            : {};
        const fov = cameraConfig.fov || 50;
        const camera = new THREE.PerspectiveCamera(fov, canvas.clientWidth / canvas.clientHeight, 0.01, 10000);
        camera.position.set(5, 5, 5);
        scene.add(camera);

        // Matcap texture.
        const matcap = matcapTextureFile
            ? new THREE.TextureLoader(manager).load(matcapTextureFile)
            : null;

        // Environment lighting (IBL) + optional extra lights.
        setupEnvironment(scene, renderer);
        setupLights(scene, config.lights);

        // Controls (OrbitControls).
        const control = new OrbitControls(camera, canvas);
        control.enableDamping = true;
        control.enablePan = false;
        control.enabled = !config.animation;

        // Auto-rotate on load (set to false to disable).
        var initialAutoRotate = true;
        control.autoRotate = initialAutoRotate;
        control.autoRotateSpeed = 2.0;

        // Callback after model is added to scene.
        function onModelLoaded(object) {
            fitCameraToObject(camera, object, control);
            hideProgress(viewerElement, progressLoader);
            if (config.animation && typeof gsap !== 'undefined') {
                animateCamera(camera, control, config);
            } else {
                control.enabled = true;
            }
        }

        // Load model.
        const anisotropy = renderer.capabilities.getMaxAnisotropy();
        loadModel(options, scene, manager, modelScale, matcap, anisotropy, onModelLoaded);

        // Buttons.
        createModeButtons(viewerElement, control, camera, canvas);
        createFullscreenButton(viewerElement);

        // Prevent page scroll/move when interacting with the viewer.
        preventPageScroll(viewerElement);

        // Render loop.
        (function tick() {
            if (control.enabled) {
                control.update();
            }
            renderer.render(scene, camera);
            window.requestAnimationFrame(tick);
        })();

        // Resize.
        function updateSizes() {
            camera.aspect = canvas.clientWidth / canvas.clientHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(canvas.clientWidth, canvas.clientHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        }

        manager.onLoad = updateSizes;
        window.addEventListener('resize', updateSizes);
    }

    // ==================== //
    // Model loading        //
    // ==================== //

    function loadModel(options, scene, manager, modelScale, matcap, anisotropy, onLoaded) {
        const mediaType = options.mediaType || 'model/gltf-binary';

        if (mediaType === 'model/gltf-binary'
            || mediaType === 'model/gltf+json'
            || mediaType === 'model/gltf'
        ) {
            new GLTFLoader(manager).load(options.source, function (gltf) {
                const model = gltf.scene;
                model.scale.set(modelScale, modelScale, modelScale);
                applyMaterials(model, matcap, anisotropy);
                scene.add(model);
                onLoaded(model);
            });
        } else if (mediaType === 'model/obj') {
            manager.addHandler(/\.dds$/i, new DDSLoader());
            if (options.mtl && options.mtl.length) {
                new MTLLoader(manager).load(options.mtl[0], function (materials) {
                    materials.preload();
                    new OBJLoader(manager).setMaterials(materials).load(options.source, function (object) {
                        scene.add(object);
                        onLoaded(object);
                    });
                });
            } else {
                new OBJLoader(manager).load(options.source, function (object) {
                    scene.add(object);
                    onLoaded(object);
                });
            }
        } else if (mediaType === 'model/vnd.collada+xml') {
            if (options.mtl && options.mtl.length) {
                new MTLLoader(manager).load(options.mtl[0], function (materials) {
                    materials.preload();
                    new ColladaLoader(manager).load(options.source, function (collada) {
                        scene.add(collada.scene);
                        onLoaded(collada.scene);
                    });
                });
            } else {
                new ColladaLoader(manager).load(options.source, function (collada) {
                    scene.add(collada.scene);
                    onLoaded(collada.scene);
                });
            }
        } else if (mediaType === 'model/vnd.filmbox') {
            new FBXLoader().load(options.source, function (object) {
                if (object.animations && object.animations.length) {
                    const mixer = new THREE.AnimationMixer(object);
                    mixer.clipAction(object.animations[0]).play();
                }
                scene.add(object);
                onLoaded(object);
            });
        } else {
            console.warn('Model Viewer: unsupported media type "' + mediaType + '".');
        }
    }

    function applyMaterials(model, matcap, anisotropy) {
        model.traverse(function (child) {
            if (!child.isMesh) return;
            if (matcap) {
                child.material = new THREE.MeshMatcapMaterial({
                    map: child.material.map,
                    matcap: matcap,
                    color: new THREE.Color(0xffffff),
                    side: THREE.DoubleSide,
                });
            }
            // Keep original GLTF materials (PBR + side) when no matcap.
            if (child.material.map) {
                child.material.map.anisotropy = anisotropy;
            }
            child.castShadow = true;
            child.receiveShadow = true;
        });
    }

    // ==================== //
    // Lights               //
    // ==================== //

    function setupEnvironment(scene, renderer) {
        var pmrem = new THREE.PMREMGenerator(renderer);
        pmrem.compileEquirectangularShader();
        var roomEnv = new RoomEnvironment(renderer);
        var envTexture = pmrem.fromScene(roomEnv).texture;
        scene.environment = envTexture;
        roomEnv.dispose();
        pmrem.dispose();
    }

    function setupLights(scene, lightsConfig) {
        // Environment (IBL) handles base lighting.
        // Extra lights only when explicitly configured.
        if (!lightsConfig || !lightsConfig.length) {
            return;
        }

        const lights = Array.isArray(lightsConfig) ? lightsConfig : [lightsConfig];
        lights.forEach(function (cfg) {
            const color = cfg.color !== undefined ? cfg.color : 0xffffff;
            const intensity = cfg.intensity !== undefined ? cfg.intensity : 1;
            const pos = cfg.position || { x: 0, y: 0, z: 0 };

            if (cfg.type === 'AmbientLight') {
                scene.add(new THREE.AmbientLight(color, intensity));
            } else if (cfg.type === 'PointLight') {
                const light = new THREE.PointLight(color, intensity);
                light.position.set(pos.x, pos.y, pos.z);
                scene.add(light);
            } else if (cfg.type === 'DirectionalLight') {
                const light = new THREE.DirectionalLight(color, intensity);
                light.position.set(pos.x, pos.y, pos.z);
                scene.add(light);
            } else if (cfg.type === 'architecture') {
                scene.add(new THREE.AmbientLight(0xffffff, 0.85));
                const group = new THREE.Group();
                for (let j = 1; j <= 7; j++) {
                    for (let i = 1; i <= 5; i++) {
                        const pl = new THREE.PointLight(0xffffff, 0.009);
                        pl.position.set(i * 6 - 20, 2, j * 8 - 30);
                        group.add(pl);
                    }
                }
                group.rotation.y = -Math.PI / 25;
                scene.add(group);
            }
        });
    }

    // ==================== //
    // Animation            //
    // ==================== //

    function animateCamera(camera, control, config) {
        if (!config.animation || typeof gsap === 'undefined') return;

        const duration = 2;
        control.enabled = false;

        gsap.to(camera.position, { x: 0, duration: duration });
        gsap.to(camera.position, { y: 0, duration: duration });
        gsap.to(camera.position, { z: 30, duration: duration / 2 });
        gsap.to(camera.quaternion, { x: 0, duration: duration });
        gsap.to(camera.quaternion, { y: 0, duration: duration });
        gsap.to(camera.quaternion, { z: 0, duration: duration });

        control.target.set(0, 0, 0);
        camera.lookAt(0, 0, 0);

        setTimeout(function () {
            control.enabled = true;
        }, duration * 1000);
    }

    // ==================== //
    // UI                   //
    // ==================== //

    function createProgressUI(viewerElement, manager) {
        const loader = document.createElement('div');
        loader.className = 'loader';

        const msg = document.createElement('p');
        msg.textContent = 'Loading';
        loader.appendChild(msg);

        const progress = document.createElement('div');
        progress.className = 'progress';
        const bar = document.createElement('div');
        bar.className = 'progress-bar';
        progress.appendChild(bar);
        loader.appendChild(progress);
        viewerElement.appendChild(loader);

        manager.onProgress = function (item, loaded, total) {
            bar.style.width = (loaded / total * 100) + '%';
        };
        manager.onError = function (url) {
            viewerElement.innerHTML = '<div class="error" style="color:#a91919">Cannot load:<ul><li>'
                + url + '</li></ul></div>';
        };

        return loader;
    }

    function hideProgress(viewerElement, progressLoader) {
        setTimeout(function () {
            progressLoader.style.opacity = 0;
            setTimeout(function () {
                if (progressLoader.parentNode === viewerElement) {
                    viewerElement.removeChild(progressLoader);
                }
            }, 200);
        }, 500);
    }

    function createModeButtons(viewerElement, control, camera, canvas) {
        if (viewerElement.querySelector('.button-mode1')) return;

        var isEyeMode = true;
        var lookAround = createLookAround(camera, canvas);

        // Stop auto-rotate on first user interaction.
        canvas.addEventListener('pointerdown', function stopSpin() {
            control.autoRotate = false;
            canvas.removeEventListener('pointerdown', stopSpin);
        });

        const btn1 = document.createElement('div');
        btn1.className = 'model-button button-mode1';
        viewerElement.appendChild(btn1);

        const btn2 = document.createElement('div');
        btn2.className = 'model-button button-mode2';
        viewerElement.appendChild(btn2);

        function toggleMode() {
            control.autoRotate = false;
            isEyeMode = !isEyeMode;
            control.enabled = isEyeMode;
            lookAround.enabled = !isEyeMode;
            btn1.style.opacity = isEyeMode ? '1' : '0';
            btn1.style.pointerEvents = isEyeMode ? 'all' : 'none';
            btn2.style.opacity = isEyeMode ? '0' : '1';
            btn2.style.pointerEvents = isEyeMode ? 'none' : 'all';
        }

        btn1.addEventListener('click', toggleMode);
        btn2.addEventListener('click', toggleMode);
    }

    /**
     * Look-around controls: camera stays in place, user
     * rotates the view direction. Scroll moves forward/back.
     */
    function createLookAround(camera, canvas) {
        var state = { enabled: false };
        var isDown = false;
        var prevX = 0;
        var prevY = 0;
        var sensitivity = 0.003;
        var euler = new THREE.Euler(0, 0, 0, 'YXZ');

        canvas.addEventListener('pointerdown', function (e) {
            if (!state.enabled || e.button !== 0) return;
            isDown = true;
            prevX = e.clientX;
            prevY = e.clientY;
        });

        canvas.addEventListener('pointermove', function (e) {
            if (!state.enabled || !isDown) return;
            var dx = (e.clientX - prevX) * sensitivity;
            var dy = (e.clientY - prevY) * sensitivity;
            prevX = e.clientX;
            prevY = e.clientY;
            euler.setFromQuaternion(camera.quaternion);
            euler.y -= dx;
            euler.x -= dy;
            euler.x = Math.max(-Math.PI / 2, Math.min(Math.PI / 2, euler.x));
            camera.quaternion.setFromEuler(euler);
        });

        canvas.addEventListener('pointerup', function () {
            isDown = false;
        });

        canvas.addEventListener('wheel', function (e) {
            if (!state.enabled) return;
            var dir = new THREE.Vector3();
            camera.getWorldDirection(dir);
            camera.position.addScaledVector(dir, -e.deltaY * 0.1);
        }, { passive: true });

        return state;
    }

    function createFullscreenButton(viewerElement) {
        if (viewerElement.querySelector('.button-fullscreen')) return;

        const btn = document.createElement('div');
        btn.className = 'model-button button-fullscreen';
        viewerElement.appendChild(btn);
    }

    function preventPageScroll(viewerElement) {
        viewerElement.addEventListener('wheel', function (e) {
            e.preventDefault();
        }, { passive: false });
        viewerElement.addEventListener('pointerdown', function (e) {
            if (e.target.closest('.model-button')) return;
            e.preventDefault();
        });
        viewerElement.style.touchAction = 'none';
    }

    // ==================== //
    // Fullscreen           //
    // ==================== //

    function toggleFullscreen() {
        const viewer = $(this).closest('.model-viewer');
        if (!document.fullscreenElement) {
            $('body').addClass('fullscreen');
            viewer.addClass('fullscreen').css({ height: '', width: '' });
            document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    }

    $('body').on('click', '.model-viewer .button-fullscreen', toggleFullscreen);

    $(document).on('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            $('body').removeClass('fullscreen');
            $('.model-viewer').removeClass('fullscreen');
        }
    });

    // Init all viewers.
    modelViewerOptions.forEach(createScene);
});
