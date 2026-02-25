<?php
/**
 * Plugin Name: My Custom Snippets
 * Description: 테마 파일 수정 없이 헤더, 푸터, 또는 PHP 실행 영역에 코드를 추가하는 플러그인입니다.
 * Version: 1.0
 * Author: taeho kim (unicenter@naver.com)
 * Site : https://blogger.pe.kr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 직접 접근 방지
}

// 1. 관리자 메뉴 추가
add_action( 'admin_menu', 'mcs_add_admin_menu' );
function mcs_add_admin_menu() {
    add_menu_page(
        'Simple Code Snippet Manager', // 페이지 제목
        'Code Snippets Manager', // 메뉴 이름
        'manage_options', // 권한
        'my-custom-snippets', // 슬러그
        'mcs_options_page_html', // 콜백 함수
        'dashicons-editor-code', // 아이콘
        100 // 메뉴 위치
    );
}

// 2. 스니펫 실행 로직 (헤더, 푸터, PHP 실행)
add_action( 'plugins_loaded', 'mcs_execute_snippets' );
function mcs_execute_snippets() {
    $snippets = get_option( 'mcs_snippets', array() );

    if ( empty( $snippets ) ) {
        return;
    }

    foreach ( $snippets as $snippet ) {
        if ( ! isset( $snippet['active'] ) || ! $snippet['active'] ) {
            continue;
        }

        $code = stripslashes( $snippet['code'] );
        $location = $snippet['location'];

        switch ( $location ) {
            case 'wp_head':
                add_action( 'wp_head', function() use ( $code ) {
                    echo "<!-- Custom Snippet Start -->\n" . $code . "\n<!-- Custom Snippet End -->\n";
                }, 99 );
                break;

            case 'wp_footer':
                add_action( 'wp_footer', function() use ( $code ) {
                    echo "<!-- Custom Snippet Start -->\n" . $code . "\n<!-- Custom Snippet End -->\n";
                }, 99 );
                break;

            case 'php_init':
                // 주의: PHP 코드는 eval()을 통해 실행되므로 문법 오류 시 사이트가 멈출 수 있습니다.
                // 주의: 함수 내부에서 실행되므로, 변수 선언 시 'global $var;'를 사용하지 않으면 지역 변수가 됩니다.
                try {
                    // PHP 태그가 포함되어 있다면 제거하고 실행
                    $clean_code = preg_replace( '/^<\?php|<\?/', '', $code );
                    $clean_code = preg_replace( '/\?>$/', '', $clean_code );
                    eval( $clean_code );
                } catch ( Throwable $e ) {
                    error_log( 'Snippet Error: ' . $e->getMessage() );
                }
                break;
        }
    }
}

// 3. 관리자 페이지 HTML 및 저장 로직
function mcs_options_page_html() {
    // 저장 로직
    if ( isset( $_POST['mcs_save_snippet'] ) && check_admin_referer( 'mcs_save_action', 'mcs_nonce_field' ) ) {
        $snippets = get_option( 'mcs_snippets', array() );
        
        $edit_id = isset( $_POST['mcs_id'] ) ? $_POST['mcs_id'] : '';

        if ( ! empty( $edit_id ) ) {
            // 기존 스니펫 수정
            foreach ( $snippets as $key => $snippet ) {
                if ( $snippet['id'] == $edit_id ) {
                    $snippets[$key]['title']    = sanitize_text_field( $_POST['mcs_title'] );
                    $snippets[$key]['code']     = $_POST['mcs_code'];
                    $snippets[$key]['location'] = sanitize_text_field( $_POST['mcs_location'] );
                    break;
                }
            }
            echo '<div class="updated"><p>스니펫이 수정되었습니다.</p></div>';
        } else {
            // 새 스니펫 추가
            $new_snippet = array(
                'id'       => uniqid(),
                'title'    => sanitize_text_field( $_POST['mcs_title'] ),
                'code'     => $_POST['mcs_code'], // 코드는 sanitize 하지 않음 (스크립트 허용)
                'location' => sanitize_text_field( $_POST['mcs_location'] ),
                'active'   => 1 // 기본값 활성
            );
            $snippets[] = $new_snippet;
            echo '<div class="updated"><p>스니펫이 저장되었습니다.</p></div>';
        }
        update_option( 'mcs_snippets', $snippets );
    }

    // 삭제 로직
    if ( isset( $_GET['delete'] ) && isset( $_GET['id'] ) ) {
        $snippets = get_option( 'mcs_snippets', array() );
        foreach ( $snippets as $key => $val ) {
            if ( $val['id'] == $_GET['id'] ) {
                unset( $snippets[$key] );
            }
        }
        update_option( 'mcs_snippets', array_values( $snippets ) );
        echo '<div class="updated"><p>스니펫이 삭제되었습니다.</p></div>';
    }

    // 활성/비활성 토글 로직
    if ( isset( $_GET['toggle'] ) && isset( $_GET['id'] ) ) {
        $snippets = get_option( 'mcs_snippets', array() );
        foreach ( $snippets as $key => $val ) {
            if ( $val['id'] == $_GET['id'] ) {
                // 현재 상태 반전 (없으면 1로 간주 후 0으로, 있으면 반대로)
                $current_state = isset($snippets[$key]['active']) ? $snippets[$key]['active'] : 1;
                $snippets[$key]['active'] = $current_state ? 0 : 1;
            }
        }
        update_option( 'mcs_snippets', array_values( $snippets ) );
        echo '<div class="updated"><p>스니펫 상태가 변경되었습니다.</p></div>';
    }

    $snippets = get_option( 'mcs_snippets', array() );

    // 수정 모드 데이터 준비
    $edit_data = array( 'id' => '', 'title' => '', 'code' => '', 'location' => '' );
    $is_edit_mode = false;
    if ( isset( $_GET['edit'] ) && isset( $_GET['id'] ) ) {
        foreach ( $snippets as $snippet ) {
            if ( $snippet['id'] == $_GET['id'] ) {
                $edit_data = $snippet;
                $is_edit_mode = true;
                break;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>내 코드 스니펫 관리</h1>
        <p>테마의 functions.php를 수정하지 않고 코드를 추가하세요.</p>

        <!-- 스니펫 추가/수정 폼 -->
        <div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
            <h2><?php echo $is_edit_mode ? '스니펫 수정' : '새 스니펫 추가'; ?></h2>
            <form method="post" action="?page=my-custom-snippets">
                <?php wp_nonce_field( 'mcs_save_action', 'mcs_nonce_field' ); ?>
                <input type="hidden" name="mcs_id" value="<?php echo esc_attr( $edit_data['id'] ); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mcs_title">제목</label></th>
                        <td><input name="mcs_title" type="text" id="mcs_title" class="regular-text" value="<?php echo esc_attr( $edit_data['title'] ); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcs_location">위치 (Hook)</label></th>
                        <td>
                            <select name="mcs_location" id="mcs_location">
                                <option value="wp_head" <?php selected( $edit_data['location'], 'wp_head' ); ?>>Header (wp_head) - CSS/JS/Meta 태그</option>
                                <option value="wp_footer" <?php selected( $edit_data['location'], 'wp_footer' ); ?>>Footer (wp_footer) - JS/Tracking 코드</option>
                                <option value="php_init" <?php selected( $edit_data['location'], 'php_init' ); ?>>PHP 실행코드 (functions.php에 추가)</option>
                            </select>
                            <p class="description">PHP 실행 선택 시 `&lt;?php` 태그 없이 코드만 입력하세요.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcs_code">코드</label></th>
                        <td>
                            <textarea name="mcs_code" id="mcs_code" rows="10" cols="50" class="large-text code" placeholder="여기에 코드를 입력하세요..."><?php echo esc_textarea( stripslashes( $edit_data['code'] ) ); ?></textarea>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="mcs_save_snippet" id="submit" class="button button-primary" value="<?php echo $is_edit_mode ? '스니펫 수정' : '스니펫 저장'; ?>">
                    <?php if ( $is_edit_mode ) : ?>
                        <a href="?page=my-custom-snippets" class="button">취소</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- 저장된 스니펫 목록 -->
        <h2>저장된 스니펫 목록</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>제목</th>
                    <th>위치</th>
                    <th>코드 미리보기</th>
                    <th>상태</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $snippets ) ) : ?>
                    <tr><td colspan="4">저장된 스니펫이 없습니다.</td></tr>
                <?php else : ?>
                    <?php foreach ( $snippets as $snippet ) : ?>
                        <?php $is_active = isset($snippet['active']) ? $snippet['active'] : 1; ?>
                        <tr>
                            <td><strong><?php echo esc_html( $snippet['title'] ); ?></strong></td>
                            <td><?php echo esc_html( $snippet['location'] ); ?></td>
                            <td><code><?php echo esc_html( mb_substr( stripslashes( $snippet['code'] ), 0, 50 ) ) . '...'; ?></code></td>
                            <td>
                                <?php echo $is_active ? '<span style="color:green; font-weight:bold;">활성</span>' : '<span style="color:gray;">비활성</span>'; ?>
                            </td>
                            <td>
                                <a href="?page=my-custom-snippets&edit=1&id=<?php echo $snippet['id']; ?>" class="button button-small">수정</a>
                                <a href="?page=my-custom-snippets&toggle=1&id=<?php echo $snippet['id']; ?>" class="button button-small">
                                    <?php echo $is_active ? '미적용' : '적용'; ?>
                                </a>
                                <a href="?page=my-custom-snippets&delete=1&id=<?php echo $snippet['id']; ?>" class="button button-small button-link-delete" onclick="return confirm('정말 삭제하시겠습니까?');">삭제</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}